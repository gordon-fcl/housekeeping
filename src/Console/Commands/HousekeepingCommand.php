<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\table;

class HousekeepingCommand extends Command
{
    protected $signature = 'housekeeping:list {--tag= : The label to filter issues by}';

    protected $description = 'Browse and manage GitHub issues from the terminal';

    private Housekeeping $housekeeping;

    private string $repo;

    public function handle(Housekeeping $housekeeping): int
    {
        $this->housekeeping = $housekeeping;

        $repos = collect(spin(
            fn () => $housekeeping->getRepos(),
            'Fetching repositories...'
        ));

        if ($repos->isEmpty()) {
            $this->warn('No repositories found for the current user.');

            return self::FAILURE;
        }

        $this->repo = $this->selectFrom($repos, 'Choose a repository:');
        $this->info("Selected: {$this->repo}");

        $this->labelLoop();

        return self::SUCCESS;
    }

    private function labelLoop(): void
    {
        $data = spin(fn () => [
            'labels' => $this->housekeeping->getRepoLabels($this->repo),
            'issues' => $this->housekeeping->getAllOpenIssues($this->repo),
        ], 'Fetching labels and issues...');

        $labels = collect($data['labels']);
        $allIssues = collect($data['issues']);

        if ($labels->isEmpty()) {
            $this->warn("No labels found for {$this->repo}.");

            return;
        }

        // Count issues per label
        $issueCounts = $allIssues
            ->flatMap(fn (array $issue) => collect($issue['labels'] ?? [])->pluck('name'))
            ->countBy()
            ->all();

        $tag = $this->option('tag');

        if (! $tag) {
            $choices = $labels->mapWithKeys(fn (array $label) => [
                $label['name'] => $label['name'].' ('.($issueCounts[$label['name']] ?? 0).')',
            ])->toArray();

            $tag = select(
                label: "Choose a label from {$this->repo}:",
                options: $choices,
                scroll: 10,
            );
        }

        $this->info("Selected: {$tag}");

        $issues = $allIssues->filter(fn (array $issue) => collect($issue['labels'] ?? [])
            ->pluck('name')
            ->contains($tag)
        );

        if ($issues->isEmpty()) {
            $this->warn("No issues found with label \"{$tag}\".");
            $this->labelLoop();

            return;
        }

        $this->issueList($issues);
    }

    private function issueList(Collection $issues): void
    {
        table(
            headers: ['#', 'Title', 'Description'],
            rows: $issues->map(fn (array $issue) => [
                $issue['number'],
                $issue['title'],
                str($issue['body'] ?? '')->limit(80),
            ])->toArray()
        );

        $choices = $issues->mapWithKeys(fn (array $issue) => [
            $issue['number'] => "#{$issue['number']} {$issue['title']}",
        ])->toArray();

        $selected = select(
            label: 'Select an issue to view:',
            options: $choices,
            scroll: 10,
        );

        $issue = $issues->firstWhere('number', (int) $selected);

        $this->issueDetail($issue);
    }

    /** @param array<string, mixed> $issue */
    private function issueDetail(array $issue): void
    {
        $this->newLine();
        $this->line("<info>#{$issue['number']}</info> <comment>{$issue['title']}</comment>");
        $this->newLine();

        table(
            headers: ['Field', 'Value'],
            rows: [
                ['Author', $issue['user']['login'] ?? ''],
                ['Assignees', collect($issue['assignees'] ?? [])->pluck('login')->implode(', ') ?: 'None'],
                ['Labels', collect($issue['labels'] ?? [])->pluck('name')->implode(', ') ?: 'None'],
                ['Created', $issue['created_at'] ?? ''],
                ['Comments', (string) ($issue['comments'] ?? 0)],
            ]
        );

        $this->newLine();
        $this->line($issue['body'] ?? 'No description.');
        $this->newLine();

        $this->issueActions($issue);
    }

    /** @param array<string, mixed> $issue */
    private function issueActions(array $issue): void
    {
        $number = $issue['number'];

        $action = select(
            label: 'What next?',
            options: [
                'comments' => 'View comments',
                'start' => 'Start work on this issue',
                'export' => 'Export as JSON',
                'back' => 'Back to issue list',
                'exit' => 'Exit',
            ],
        );

        match ($action) {
            'comments' => $this->showComments($issue),
            'start' => $this->call('housekeeping:start', ['repo' => $this->repo, 'issue' => $number]),
            'export' => $this->call('housekeeping:export', ['repo' => $this->repo, 'issue' => $number, '--pretty' => true]),
            'back' => $this->labelLoop(),
            'exit' => null,
        };
    }

    /** @param array<string, mixed> $issue */
    private function showComments(array $issue): void
    {
        $number = (int) $issue['number'];

        $comments = collect(spin(
            fn () => $this->housekeeping->getComments($this->repo, $number),
            'Fetching comments...'
        ));

        if ($comments->isEmpty()) {
            $this->info('No comments.');
        } else {
            $this->newLine();
            foreach ($comments as $comment) {
                $this->line("<info>{$comment['user']['login']}</info> ({$comment['created_at']}):");
                $this->line($comment['body'] ?? '');
                $this->newLine();
            }
        }

        $this->issueActions($issue);
    }

    private function selectFrom(Collection $items, string $label): string
    {
        $names = $items->pluck('name')->toArray();

        return suggest(
            label: $label,
            options: $names,
            placeholder: "E.g. {$names[0]}",
            scroll: 6,
            required: true,
        );
    }
}
