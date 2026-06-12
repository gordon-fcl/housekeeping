<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class StalenessCommand extends Command
{
    protected $signature = 'housekeeping:stale
        {repo : The repository name}
        {--days=90 : How far back to search commits}';

    protected $description = 'Detect open issues that may have been resolved by recent commits';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');

        $issues = spin(
            fn (): array => $housekeeping->getAllOpenIssues($repo),
            'Fetching open issues...'
        );

        if (empty($issues)) {
            $this->info('No open issues found.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $since = now()->subDays($days)->format('Y-m-d');

        $log = $this->gitLog($since);

        if ($log === '') {
            $this->info("No commits found in the last {$days} days.");

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($issues as $issue) {
            $number = $issue['number'];
            $matches = $this->findReferences($log, $number);

            if ($matches > 0) {
                $rows[] = [
                    "#{$number}",
                    str($issue['title'])->limit(50),
                    (string) $matches,
                    'Possibly resolved',
                ];
            }
        }

        if ($rows === []) {
            $this->info('No open issues appear to be referenced in recent commits.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("<comment>Issues referenced in commits since {$since}:</comment>");
        $this->newLine();

        table(
            headers: ['Issue', 'Title', 'References', 'Status'],
            rows: $rows,
        );

        $this->newLine();
        $this->info('These issues may have been resolved without being closed.');

        return self::SUCCESS;
    }

    private function gitLog(string $since): string
    {
        $command = sprintf(
            'git log --since=%s --oneline --all 2>/dev/null',
            escapeshellarg($since)
        );

        return (string) shell_exec($command);
    }

    private function findReferences(string $log, int $number): int
    {
        $pattern = sprintf('/(?:#%1$d|\bfix(?:es|ed)?\s+%1$d|\bclose[sd]?\s+%1$d|\bissue[- ]?%1$d)\b/i', $number);

        return preg_match_all($pattern, $log);
    }
}
