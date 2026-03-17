<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class ShowCommand extends Command
{
    protected $signature = 'housekeeping:show
        {repo : The repository name}
        {issue : The issue number}
        {--brief : Show only the title and truncated description}';

    protected $description = 'Display a GitHub issue with its comments';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');
        $number = (int) $this->argument('issue');

        $issue = spin(
            fn () => $housekeeping->getIssue($repo, $number),
            'Fetching issue...'
        );

        if ($this->option('brief')) {
            $this->line("<info>#{$issue['number']}</info> {$issue['title']}");
            $this->line(str($issue['body'] ?? '')->limit(250));

            return self::SUCCESS;
        }

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

        $comments = spin(
            fn () => $housekeeping->getComments($repo, $number),
            'Fetching comments...'
        );

        if (empty($comments)) {
            $this->info('No comments.');

            return self::SUCCESS;
        }

        $this->line('<comment>Comments:</comment>');
        $this->newLine();

        foreach ($comments as $comment) {
            $this->line("<info>{$comment['user']['login']}</info> ({$comment['created_at']}):");
            $this->line($comment['body'] ?? '');
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
