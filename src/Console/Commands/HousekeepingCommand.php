<?php

declare(strict_types=1);

namespace Ediblemanager\Housekeeping\Console\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\table;

use Ediblemanager\Housekeeping\Housekeeping;
use Illuminate\Support\Collection;

class HousekeepingCommand extends Command
{
    protected $signature = "housekeeping:list {--tag=housekeeping}'";

    protected $description = "Fetch all issues matching the given tag";

    private $housekeeping;

    public function handle(Housekeeping $housekeeping): void
    {
        $this->housekeeping = $housekeeping;

        // If we want, we can fetch the colour of the issue.
        //$labels = $tags->pluck('name', 'color')->map(fn ($name, $color) => "<fg=black;bg=#$color>$name</>")->toArray();
        $labels = $this->getLabels();
        $selectionLabel = $this->getSelection($labels);

        // Output chosen label to console
        $this->info("You chose $selectionLabel!");

        // Fetch all issues from current repo that have the selected label (the repo this package in installed in)
        $issues = $this->getIssues($selectionLabel);

        // Fetch all issues from current repo that have the selected label (the repo this package in installed in)
        $this->showIssues($issues);
    }

    /**
     * Fetch all labels from the current repo
     *
     * @return Collection
     */
    private function getLabels(): Collection
    {
        // Fetch all labels from current repo (the repo this package in installed in)
        // @phpstan-ignore-next-line
        return collect(spin(
            fn () => $this->housekeeping->getIssueLabels(),
            'Fetching labels...'
        ));
    }

    /**
     * Show all the labels and ask the user to choose one.
     *
     * @return String
     */
    private function getSelection(Collection $labels): string
    {
        // Show all labels, get user choice
        return select(
            label: 'Choose a tag from the list below (fetched from this repo on GH)',
            options: $labels->pluck('name')->toArray(),
            scroll: 6,
            required: true,
        );
    }

    /**
     * Fetch all issues from the current repo that have the selected label
     *
     * @return Collection
     */
    private function getIssues(string $selectionLabel): Collection
    {
        return collect(spin(
            fn () => $this->housekeeping->getIssues($selectionLabel),
            'Fetching issues...'
        ));
    }

    /**
     * Output the results from getIssues as a table, showing the issue name, a concatenated description and the issue number
     *
     * @return void
     */

    private function showIssues(Collection $issues): void
    {
        table(
            headers: ['Issue', 'Description', 'URL'],
            rows: $issues->map(fn ($issue) => [
                $issue['title'],
                substr($issue['body'], 0, 150),
                "\033]8;;{$issue['html_url']}\033\\Link to issue\033]8;;\033\\"
            ])->toArray()
        );
    }
}
