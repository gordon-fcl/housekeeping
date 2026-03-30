<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class LabelStatsCommand extends Command
{
    protected $signature = 'housekeeping:label-stats
        {repo : The repository name}';

    protected $description = 'Display issue counts and task completion per label';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');

        $labels = spin(
            fn (): array => $housekeeping->getRepoLabels($repo),
            'Fetching labels...'
        );

        if (empty($labels)) {
            $this->info('No labels found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($labels as $label) {
            $name = $label['name'];

            $issues = spin(
                fn (): array => $housekeeping->getIssues($repo, $name),
                "Counting issues for {$name}..."
            );

            $total = 0;
            $done = 0;

            foreach ($issues as $issue) {
                preg_match_all('/- \[([ x])\]/', $issue['body'] ?? '', $matches);
                $total += count($matches[1]);
                $done += count(array_filter($matches[1], fn (string $m): bool => $m === 'x'));
            }

            $tasks = $total > 0 ? "{$done}/{$total}" : '-';

            $rows[] = [$name, (string) count($issues), $tasks];
        }

        table(
            headers: ['Label', 'Open Issues', 'Tasks'],
            rows: $rows,
        );

        return self::SUCCESS;
    }
}
