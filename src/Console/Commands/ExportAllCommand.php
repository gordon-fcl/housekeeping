<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ExportAllCommand extends Command
{
    protected $signature = 'housekeeping:export-all
        {repo : The repository name}
        {--output= : File path to write JSON to (skips interactive prompt)}';

    protected $description = 'Export all open issues and their comments as JSON';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');

        $issues = spin(
            fn (): array => $housekeeping->getAllOpenIssues($repo),
            'Fetching issues...'
        );

        if (empty($issues)) {
            $this->info('No open issues found.');

            return self::SUCCESS;
        }

        $output = [];

        foreach ($issues as $issue) {
            $number = $issue['number'];

            $comments = spin(
                fn (): array => $housekeeping->getComments($repo, $number),
                "Fetching comments for #{$number}..."
            );

            $output[] = [
                'number' => $number,
                'title' => $issue['title'],
                'body' => $issue['body'] ?? null,
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
        }

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $default = base_path("{$repo}-issues.json");
        $path = $this->option('output') ?? text(
            label: 'Save JSON to',
            default: $default,
            required: true,
            validate: fn (string $value): ?string => is_dir(dirname($value))
                ? null
                : 'Directory does not exist.',
        );

        file_put_contents($path, $json."\n");
        $this->info('Exported '.count($output)." issues to {$path}");

        return self::SUCCESS;
    }
}
