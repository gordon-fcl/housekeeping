<?php

declare(strict_types=1);

namespace FCL\Housekeeping;

use GrahamCampbell\GitHub\Facades\GitHub;

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
        $username = $this->username();

        /** @phpstan-ignore-next-line */
        return GitHub::api('repo')->labels()->all($username, $repo);
    }

    /** @return array<int, array<string, mixed>> */
    public function getIssues(string $repo, ?string $label = null): array
    {
        $username = $this->username();
        $label ??= config('housekeeping.label', 'housekeeping');

        /** @phpstan-ignore-next-line */
        return GitHub::api('issues')->all($username, $repo, [
            'state' => 'open',
            'labels' => $label,
        ]);
    }

    private function username(): string
    {
        /** @phpstan-ignore-next-line */
        return GitHub::api('currentUser')->show()['login'];
    }
}
