<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\spin;

class ExportCommand extends Command
{
    protected $signature = 'housekeeping:export
        {repo : The repository name}
        {issue : The issue number}
        {--pretty : Pretty-print the JSON output}';

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

        $flags = $this->option('pretty') ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_SLASHES;

        $this->line(json_encode($output, $flags));

        return self::SUCCESS;
    }
}
