<?php

declare(strict_types=1);

namespace FCL\Housekeeping;

use GrahamCampbell\GitHub\Facades\GitHub;
use Illuminate\Support\Str;

final class Housekeeping
{
    /** @return array<int, array<string, mixed>> */
    public function getRepos(): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::me()->repositories();
    }

    /** @return array<int, array<string, mixed>> */
    public function getRepoLabels(string $repo): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('repo')->labels()->all($this->username(), $repo);
    }

    /** @return array<int, array<string, mixed>> */
    public function getIssues(string $repo, ?string $label = null): array
    {
        $label ??= config('housekeeping.label', 'housekeeping');

        /** @phpstan-ignore-next-line */
        return GitHub::api('issues')->all($this->username(), $repo, [
            'state' => 'open',
            'labels' => $label,
        ]);
    }

    /** @return array<string, mixed> */
    public function getIssue(string $repo, int $number): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('issues')->show($this->username(), $repo, $number);
    }

    /** @return array<int, array<string, mixed>> */
    public function getComments(string $repo, int $number): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('issues')->comments()->all($this->username(), $repo, $number);
    }

    public function assignIssue(string $repo, int $number): void
    {
        /** @phpstan-ignore-next-line */
        GitHub::api('issues')->update($this->username(), $repo, $number, [
            'assignees' => [$this->username()],
        ]);
    }

    /**
     * Create and check out a branch for the given issue.
     *
     * @return string The branch name that was created.
     */
    public function createBranch(string $issueTitle, int $issueNumber): string
    {
        $slug = Str::slug(Str::limit($issueTitle, 40, ''));
        $branch = "housekeeping/{$issueNumber}-{$slug}";
        $base = config('housekeeping.base_branch', 'staging');

        $this->git(['checkout', $base]);
        $this->git(['pull', 'origin', $base]);
        $this->git(['checkout', '-b', $branch]);

        return $branch;
    }

    public function username(): string
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('currentUser')->show()['login'];
    }

    /**
     * @param array<int, string> $args
     */
    private function git(array $args): void
    {
        $command = 'git '.implode(' ', array_map('escapeshellarg', $args));
        $result = null;
        $output = [];

        exec($command.' 2>&1', $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException('Git command failed: '.implode("\n", $output));
        }
    }
}
