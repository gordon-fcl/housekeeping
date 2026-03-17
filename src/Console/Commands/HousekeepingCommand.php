<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\table;

class HousekeepingCommand extends Command
{
    protected $signature = 'housekeeping:list {--tag= : The label to filter issues by}';

    protected $description = 'Fetch and display GitHub issues matching a given label';

    public function handle(Housekeeping $housekeeping): int
    {
        $repos = collect(spin(
            fn () => $housekeeping->getRepos(),
            'Fetching repositories...'
        ));

        if ($repos->isEmpty()) {
            $this->warn('No repositories found for the current user.');

            return self::FAILURE;
        }

        $selectedRepo = $this->selectFrom($repos, 'Choose a repository:');
        $this->info("Selected: {$selectedRepo}");

        $labels = collect(spin(
            fn () => $housekeeping->getRepoLabels($selectedRepo),
            'Fetching labels...'
        ));

        if ($labels->isEmpty()) {
            $this->warn("No labels found for {$selectedRepo}.");

            return self::FAILURE;
        }

        $tag = $this->option('tag');
        $selectedLabel = $tag ?: $this->selectFrom($labels, "Choose a label from {$selectedRepo}:");
        $this->info("Selected: {$selectedLabel}");

        $issues = collect(spin(
            fn () => $housekeeping->getIssues($selectedRepo, $selectedLabel),
            "Fetching issues from {$selectedRepo}..."
        ));

        if ($issues->isEmpty()) {
            $this->warn("No issues found with label \"{$selectedLabel}\".");

            return self::SUCCESS;
        }

        table(
            headers: ['Issue', 'Description', 'URL'],
            rows: $issues->map(fn (array $issue) => [
                $issue['title'],
                str($issue['body'] ?? '')->limit(250),
                "\033]8;;{$issue['html_url']}\033\\Link\033]8;;\033\\",
            ])->toArray()
        );

        return self::SUCCESS;
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
