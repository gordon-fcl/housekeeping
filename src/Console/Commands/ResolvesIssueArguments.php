<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;

trait ResolvesIssueArguments
{
    private function resolveRepo(Housekeeping $housekeeping): ?string
    {
        $repo = $this->argument('repo');

        if ($repo) {
            return $repo;
        }

        $repos = collect(spin(
            fn (): array => $housekeeping->getRepos(),
            'Fetching repositories...'
        ));

        if ($repos->isEmpty()) {
            $this->warn('No repositories found.');

            return null;
        }

        $names = $repos->pluck('name')->all();

        return suggest(
            label: 'Choose a repository:',
            options: $names,
            required: true,
            validate: fn (string $value): ?string => in_array($value, $names, true)
                ? null
                : 'Please select a valid repository.',
        );
    }

    private function resolveIssueNumber(Housekeeping $housekeeping, string $repo): ?int
    {
        $issue = $this->argument('issue');

        if ($issue) {
            return (int) $issue;
        }

        $issues = spin(
            fn (): array => $housekeeping->getAllOpenIssues($repo),
            'Fetching issues...'
        );

        if (empty($issues)) {
            $this->warn('No open issues found.');

            return null;
        }

        $choices = collect($issues)->mapWithKeys(fn (array $issue): array => [
            $issue['number'] => "#{$issue['number']} {$issue['title']}",
        ])->all();

        return (int) select(
            label: 'Select an issue:',
            options: $choices,
            scroll: 10,
        );
    }
}
