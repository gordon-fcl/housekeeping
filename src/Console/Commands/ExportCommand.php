<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ExportCommand extends Command
{
    protected $signature = 'housekeeping:export
        {repo : The repository name}
        {issue : The issue number}
        {--output= : File path to write JSON to (skips interactive prompt)}';

    protected $description = 'Export a GitHub issue and its comments as JSON';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');
        $number = (int) $this->argument('issue');

        $issue = spin(
            fn (): array => $housekeeping->getIssue($repo, $number),
            'Fetching issue...'
        );

        $comments = spin(
            fn (): array => $housekeeping->getComments($repo, $number),
            'Fetching comments...'
        );

        $output = [
            'number' => $issue['number'],
            'title' => $issue['title'],
            'body' => $issue['body'],
            'author' => $issue['user']['login'] ?? null,
            'assignees' => collect($issue['assignees'] ?? [])->pluck('login')->all(),
            'labels' => collect($issue['labels'] ?? [])->pluck('name')->all(),
            'created_at' => $issue['created_at'],
            'updated_at' => $issue['updated_at'],
            'comments' => collect($comments)->map(fn (array $comment): array => [
                'author' => $comment['user']['login'] ?? null,
                'body' => $comment['body'],
                'created_at' => $comment['created_at'],
            ])->all(),
        ];

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $json = json_encode($output, $flags);

        $default = base_path("issue-{$number}.json");
        $path = $this->option('output') ?? text(
            label: 'Save JSON to',
            default: $default,
            required: true,
        );

        file_put_contents($path, $json."\n");
        $this->info("Saved to {$path}");

        return self::SUCCESS;
    }
}
