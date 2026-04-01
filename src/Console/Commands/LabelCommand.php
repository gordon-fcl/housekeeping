<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Console\Commands;

use FCL\Housekeeping\Housekeeping;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class LabelCommand extends Command
{
    protected $signature = 'housekeeping:label
        {repo : The repository name}';

    protected $description = 'Manage labels on a repository or issue';

    public function handle(Housekeeping $housekeeping): int
    {
        $repo = $this->argument('repo');

        $action = select(
            label: 'What would you like to do?',
            options: [
                'add' => 'Add labels to an issue',
                'remove' => 'Remove labels from an issue',
                'create' => 'Create a new label',
                'delete' => 'Delete a label',
            ],
        );

        return match ($action) {
            'add' => $this->addLabels($housekeeping, $repo),
            'remove' => $this->removeLabels($housekeeping, $repo),
            'create' => $this->createLabel($housekeeping, $repo),
            'delete' => $this->deleteLabel($housekeeping, $repo),
            default => self::SUCCESS,
        };
    }

    private function addLabels(Housekeeping $housekeeping, string $repo): int
    {
        $number = (int) text(
            label: 'Issue number',
            required: true,
            validate: fn (string $value): ?string => ctype_digit($value) ? null : 'Please enter a valid number.',
        );

        $labels = spin(
            fn (): array => $housekeeping->getRepoLabels($repo),
            'Fetching labels...'
        );

        $names = collect($labels)->pluck('name')->all();

        $selected = multiselect(
            label: 'Select labels to add',
            options: $names,
            required: true,
        );

        spin(
            fn () => $housekeeping->github()->addLabels($repo, $number, $selected),
            'Adding labels...'
        );

        $this->info('Labels added to issue #'.$number.'.');

        return self::SUCCESS;
    }

    private function removeLabels(Housekeeping $housekeeping, string $repo): int
    {
        $number = (int) text(
            label: 'Issue number',
            required: true,
            validate: fn (string $value): ?string => ctype_digit($value) ? null : 'Please enter a valid number.',
        );

        $issue = spin(
            fn (): array => $housekeeping->getIssue($repo, $number),
            'Fetching issue...'
        );

        $current = collect($issue['labels'] ?? [])->pluck('name')->all();

        if (empty($current)) {
            $this->info('Issue #'.$number.' has no labels.');

            return self::SUCCESS;
        }

        $selected = multiselect(
            label: 'Select labels to remove',
            options: $current,
            required: true,
        );

        spin(
            fn () => $housekeeping->github()->removeLabels($repo, $number, $selected),
            'Removing labels...'
        );

        $this->info('Labels removed from issue #'.$number.'.');

        return self::SUCCESS;
    }

    private function createLabel(Housekeeping $housekeeping, string $repo): int
    {
        $name = text(label: 'Label name', required: true);
        $colour = text(
            label: 'Colour (hex, e.g. ff0000)',
            required: true,
            validate: fn (string $value): ?string => preg_match('/^#?[0-9a-fA-F]{6}$/', $value)
                ? null
                : 'Please enter a valid 6-character hex colour.',
        );

        spin(
            fn () => $housekeeping->github()->createLabel($repo, $name, $colour),
            'Creating label...'
        );

        $this->info("Label '{$name}' created.");

        return self::SUCCESS;
    }

    private function deleteLabel(Housekeeping $housekeeping, string $repo): int
    {
        $labels = spin(
            fn (): array => $housekeeping->getRepoLabels($repo),
            'Fetching labels...'
        );

        $names = collect($labels)->pluck('name')->all();

        $name = select(
            label: 'Select label to delete',
            options: $names,
        );

        spin(
            fn () => $housekeeping->github()->deleteLabel($repo, $name),
            'Deleting label...'
        );

        $this->info("Label '{$name}' deleted.");

        return self::SUCCESS;
    }
}
