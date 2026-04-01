<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class StartCommand extends Command
{
    protected $signature = 'housekeeping:start
        {repo : The repository name}
        {issue : The issue number}';

    protected $description = 'Create a branch and assign yourself to a GitHub issue';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');
        $number = (int) $this->argument('issue');

        $issue = spin(
            fn (): array => $housekeeping->getIssue($repo, $number),
            'Fetching issue...'
        );

        $this->line("Starting work on <info>#{$number}</info> {$issue['title']}");

        $suggested = $housekeeping->suggestBranchName($issue['title'], $number);
        $branch = text(
            label: 'Branch name',
            default: $suggested,
            required: true,
        );

        try {
            $housekeeping->createBranch($branch);
        } catch (\RuntimeException $e) {
            $this->error('Git operation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        spin(
            fn () => $housekeeping->assignIssue($repo, $number),
            'Assigning issue...'
        );

        $this->newLine();
        note("Branch: {$branch}");
        note("Issue #{$number} assigned to you.");

        return self::SUCCESS;
    }
}
