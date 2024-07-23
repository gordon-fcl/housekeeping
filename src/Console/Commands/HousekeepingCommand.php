<?php

declare(strict_types=1);

namespace Ediblemanager\Housekeeping\Console\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
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

        $repos = $this->getRepos();
        $repos_question = 'Choose a repository from the list below:';
        $selected_repo = $this->getSelection($repos, $repos_question);

        // Output chosen repo to console
        $this->info("You chose $selected_repo!");

        // If we want, we can fetch the colour of the issue.
        //$labels = $tags->pluck('name', 'color')->map(fn ($name, $color) => "<fg=black;bg=#$color>$name</>")->toArray();
        $labels = $this->getRepoLabels($selected_repo);
        $labels_question = "Choose a label from the list below (fetched from {$selected_repo} on GH):";
        $selected_label = $this->getSelection($labels, $labels_question);

        // Output chosen label to console
        $this->info("You chose $selected_label!");

        // Fetch all issues from current repo that have the selected label (the repo this package in installed in)
        $issues = $this->getIssues($selected_repo, $selected_label);

        // Fetch all issues from current repo that have the selected label (the repo this package in installed in)
        $this->showIssues($issues);
    }

    /**
     * Fetch all available repos for the current user
     *
     * @return Collection
     */
    private function getRepos(): Collection
    {
        // Fetch all labels from current repo (the repo this package in installed in)
        // @phpstan-ignore-next-line
        return collect(spin(
            fn () => $this->housekeeping->getRepos(),
            'Fetching repositories...'
        ));
    }


    /**
     * Fetch all labels from the current repo
     *
     * @return Collection
     */
    private function getRepoLabels($selected_repo): Collection
    {
        // Fetch all labels from current repo (the repo this package in installed in)
        // @phpstan-ignore-next-line
        return collect(spin(
            fn () => $this->housekeeping->getRepoLabels($selected_repo),
            'Fetching labels...'
        ));
    }

    /**
     * Show all the items and ask the user to choose one.
     *
     * @return String
     */
    private function getSelection(Collection $list, $list_question): string
    {
        // Show all labels, get user choice
        return suggest(
            label: $list_question,
            options: $list->pluck('name')->toArray(),
            placeholder: "E.g. {$list[0]['name']}",
            scroll: 6,
            required: true,
        );
    }

    /**
     * Fetch all issues from the current repo that have the selected label
     *
     * @return Collection
     */
    private function getIssues(string $selected_repo, string $selectionLabel): Collection
    {
        return collect(spin(
            fn () => $this->housekeeping->getIssues($selected_repo, $selectionLabel),
            "Fetching issues from {$selected_repo}..."
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
                substr($issue['body'], 0, 250) . '...',
                "\033]8;;{$issue['html_url']}\033\\Link to issue\033]8;;\033\\"
            ])->toArray()
        );
    }
}
