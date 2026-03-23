<?php

declare(strict_types=1);

namespace FCL\Housekeeping\GitHub;

use GrahamCampbell\GitHub\Facades\GitHub;

class Client
{
    public function username(): string
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('currentUser')->show()['login'];
    }

    /** @return array<int, array<string, mixed>> */
    public function repos(): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::me()->repositories();
    }

    /** @return array<int, array<string, mixed>> */
    public function labels(string $repo): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('repo')->labels()->all($this->username(), $repo);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function issues(string $repo, array $filters = []): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('issues')->all($this->username(), $repo, array_merge(['state' => 'open'], $filters));
    }

    /** @return array<string, mixed> */
    public function issue(string $repo, int $number): array
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('issues')->show($this->username(), $repo, $number);
    }

    /** @return array<int, array<string, mixed>> */
    public function comments(string $repo, int $number): array
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
}
