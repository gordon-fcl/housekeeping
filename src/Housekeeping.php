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
     * @return array
     */
    public function getIssueLabels(): array
    {
        /** phpstan-ignore-next-line */
        return GitHub::issues()->labels()->all('ediblemanager', 'housekeeping');
    }

    /**
     * @param string $label
     * @param array $options
     * @return array
     */
    public function getIssues(string $label = "housekeeping", array $options = ['order' => 'date:asc']): array
    {
        /** phpstan-ignore-next-line */
        return GitHub::api('issue')->all('ediblemanager', 'housekeeping', ['state' => 'open', 'labels' => $label]);
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
