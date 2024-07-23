<?php

declare(strict_types=1);

namespace Ediblemanager\Housekeeping;

use GrahamCampbell\GitHub\Facades\GitHub;

/**
 * @internal
 */
final class Housekeeping
{
    /**
     * @param string $
     * @param array $options
     * @return array
     */
    public function getRepos(): array
    {
        /** phpstan-ignore-next-line */
        return GitHub::me()->repositories();
    }

    /**
     * @return array
     */
    public function getRepoLabels(string $repo): array
    {
        /** phpstan-ignore-next-line */
        $username = GitHub::api('currentUser')->show()['login'];
        return GitHub::api('repo')->labels()->all($username, $repo);
    }

    /**
     * @param string $label
     * @param array $options
     * @return array
     */
    public function getIssues(string $repo, string $label = "housekeeping", array $options = ['order' => 'date:asc']): array
    {
        /** phpstan-ignore-next-line */
        $username = GitHub::api('currentUser')->show()['login'];
        return GitHub::api('issues')->all($username, $repo, ['state' => 'open', 'labels' => $label]);
    }

    /**
     * @param string $tag
     * @return void
     */
    public function updateTag($tag): void
    {
        // We'll write the chosen tag to a file in the root dir, housekeeping.yaml
    }
}
