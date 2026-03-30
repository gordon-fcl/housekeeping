<?php

declare(strict_types=1);

namespace FCL\Housekeeping;

use FCL\Housekeeping\GitHub\Client;
use Illuminate\Support\Str;

final readonly class Housekeeping
{
    public function __construct(
        private Client $github = new Client,
    ) {}

    public function github(): Client
    {
        return $this->github;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRepos(): array
    {
        return $this->github->repos();
    }

    /** @return array<int, array<string, mixed>> */
    public function getRepoLabels(string $repo): array
    {
        return $this->github->labels($repo);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllOpenIssues(string $repo): array
    {
        return $this->github->issues($repo);
    }

    /** @return array<int, array<string, mixed>> */
    public function getIssues(string $repo, ?string $label = null): array
    {
        $label ??= config('housekeeping.label', 'housekeeping');

        return $this->github->issues($repo, ['labels' => $label]);
    }

    /** @return array<string, mixed> */
    public function getIssue(string $repo, int $number): array
    {
        return $this->github->issue($repo, $number);
    }

    /** @return array<int, array<string, mixed>> */
    public function getComments(string $repo, int $number): array
    {
        return $this->github->comments($repo, $number);
    }

    public function assignIssue(string $repo, int $number): void
    {
        $this->github->assignIssue($repo, $number);
    }

    /**
     * Generate a suggested branch name for an issue.
     */
    public function suggestBranchName(string $issueTitle, int $issueNumber): string
    {
        $slug = Str::slug(Str::limit($issueTitle, 40, ''));

        return "housekeeping/{$issueNumber}-{$slug}";
    }

    /**
     * Create and check out a branch.
     *
     * @return string The branch name that was created.
     */
    public function createBranch(string $branch): string
    {
        $base = config('housekeeping.base_branch', 'staging');

        $this->git(['checkout', $base]);
        $this->git(['pull', 'origin', $base]);
        $this->git(['checkout', '-b', $branch]);

        return $branch;
    }

    public function username(): string
    {
        return $this->github->username();
    }

    /** @param array<int, string> $args */
    private function git(array $args): void
    {
        $command = 'git '.implode(' ', array_map(escapeshellarg(...), $args));
        $result = null;
        $output = [];

        exec($command.' 2>&1', $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException('Git command failed: '.implode("\n", $output));
        }
    }
}
