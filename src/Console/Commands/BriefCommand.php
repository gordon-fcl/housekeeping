<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class BriefCommand extends Command
{
    use ResolvesIssueArguments;

    protected $signature = 'housekeeping:brief
        {repo? : The repository name}
        {issue? : The issue number}
        {--output= : File path to write the brief to (skips interactive prompt)}';

    protected $description = 'Generate an AI-ready Markdown brief for a GitHub issue';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->resolveRepo($housekeeping);
        if (! $repo) {
            return self::FAILURE;
        }

        $number = $this->resolveIssueNumber($housekeeping, $repo);
        if (! $number) {
            return self::FAILURE;
        }

        $issue = spin(
            fn (): array => $housekeeping->getIssue($repo, $number),
            'Fetching issue...'
        );

        $comments = spin(
            fn (): array => $housekeeping->getComments($repo, $number),
            'Fetching comments...'
        );

        $humanComments = array_filter($comments, fn (array $comment): bool => ! $this->isBot($comment['user']['login'] ?? ''));

        $additionalContext = '';
        if (confirm('Would you like to add additional context for the agent?', false)) {
            $additionalContext = textarea(
                label: 'Additional context or intent',
                placeholder: 'Describe what you want the agent to achieve beyond what the issue says...',
            );
        }

        $branch = $housekeeping->suggestBranchName($issue['title'], $number);
        $base = config('housekeeping.base_branch', 'staging');

        $markdown = $this->buildMarkdown($repo, $issue, $humanComments, $branch, $base, $additionalContext);

        $default = base_path("brief-{$number}.md");
        $path = $this->option('output') ?? text(
            label: 'Save brief to',
            default: $default,
            required: true,
            validate: fn (string $value): ?string => is_dir(dirname($value))
                ? null
                : 'Directory does not exist.',
        );

        file_put_contents($path, $markdown);
        note("Brief saved to {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @param  array<int, array<string, mixed>>  $comments
     */
    private function buildMarkdown(
        string $repo,
        array $issue,
        array $comments,
        string $branch,
        string $base,
        string $additionalContext,
    ): string {
        $labels = collect($issue['labels'] ?? [])->pluck('name')->implode(', ') ?: 'None';
        $assignees = collect($issue['assignees'] ?? [])->pluck('login')->implode(', ') ?: 'None';

        $md = "# Issue #{$issue['number']}: {$issue['title']}\n\n";
        $md .= "## Context\n\n";
        $md .= "| Field | Value |\n";
        $md .= "|-------|-------|\n";
        $md .= "| Repository | {$repo} |\n";
        $md .= "| Branch | `{$branch}` (from `{$base}`) |\n";
        $md .= "| Labels | {$labels} |\n";
        $md .= "| Assignees | {$assignees} |\n";
        $md .= "| Author | {$issue['user']['login']} |\n\n";

        $md .= "## Description\n\n";
        $md .= ($issue['body'] ?? 'No description.')."\n\n";

        if ($comments !== []) {
            $md .= "## Comments\n\n";
            foreach ($comments as $comment) {
                $md .= "**{$comment['user']['login']}** ({$comment['created_at']}):\n\n";
                $md .= $comment['body']."\n\n---\n\n";
            }
        }

        if ($additionalContext !== '') {
            $md .= "## Additional Context\n\n";
            $md .= $additionalContext."\n";
        }

        return $md;
    }

    private function isBot(string $login): bool
    {
        if (str_contains($login, '[bot]')) {
            return true;
        }

        $knownBots = [
            'github-actions',
            'dependabot',
            'copilot',
            'renovate',
            'codecov',
            'sonarcloud',
        ];

        return in_array($login, $knownBots, true);
    }
}
