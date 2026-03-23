<?php

declare(strict_types=1);

namespace Tests\Feature;

use DG\BypassFinals;
use FCL\Housekeeping\Housekeeping;
use Mockery\MockInterface;

beforeEach(function () {
    BypassFinals::enable();
});

it('shows issue detail with metadata', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->with('my-repo', 5)
            ->once()
            ->andReturn([
                'number' => 5,
                'title' => 'Fix broken tests',
                'body' => 'The test suite is failing on CI.',
                'user' => ['login' => 'gordon'],
                'assignees' => [['login' => 'gordon']],
                'labels' => [['name' => 'bug']],
                'created_at' => '2026-01-01T00:00:00Z',
                'comments' => 2,
            ]);

        $mock->shouldReceive('getComments')
            ->with('my-repo', 5)
            ->once()
            ->andReturn([
                [
                    'user' => ['login' => 'reviewer'],
                    'body' => 'Can you add a test for this?',
                    'created_at' => '2026-01-02T00:00:00Z',
                ],
            ]);
    });

    $this->artisan('housekeeping:show', ['repo' => 'my-repo', 'issue' => 5])
        ->expectsOutputToContain('Fix broken tests')
        ->expectsOutputToContain('The test suite is failing on CI.')
        ->expectsOutputToContain('Can you add a test for this?')
        ->assertSuccessful();
});

it('shows brief issue detail with the brief flag', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->with('my-repo', 5)
            ->once()
            ->andReturn([
                'number' => 5,
                'title' => 'Fix broken tests',
                'body' => 'The test suite is failing on CI.',
            ]);
    });

    $this->artisan('housekeeping:show', ['repo' => 'my-repo', 'issue' => 5, '--brief' => true])
        ->expectsOutputToContain('Fix broken tests')
        ->assertSuccessful();
});

it('exports an issue as json to a file', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->with('my-repo', 3)
            ->once()
            ->andReturn([
                'number' => 3,
                'title' => 'Add dark mode',
                'body' => 'Users want dark mode.',
                'user' => ['login' => 'gordon'],
                'assignees' => [],
                'labels' => [['name' => 'enhancement']],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-05T00:00:00Z',
            ]);

        $mock->shouldReceive('getComments')
            ->with('my-repo', 3)
            ->once()
            ->andReturn([
                [
                    'user' => ['login' => 'contributor'],
                    'body' => 'I can help with this.',
                    'created_at' => '2026-01-03T00:00:00Z',
                ],
            ]);
    });

    $path = sys_get_temp_dir().'/housekeeping-test-issue-3.json';

    $this->artisan('housekeeping:export', ['repo' => 'my-repo', 'issue' => 3])
        ->expectsQuestion('Save JSON to', $path)
        ->expectsOutputToContain("Saved to {$path}")
        ->assertSuccessful();

    $json = json_decode(file_get_contents($path), true);

    expect($json['title'])->toBe('Add dark mode');
    expect($json['author'])->toBe('gordon');
    expect($json['comments'][0]['body'])->toBe('I can help with this.');

    unlink($path);
});

it('starts work on an issue by creating a branch and assigning', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->with('my-repo', 7)
            ->once()
            ->andReturn([
                'number' => 7,
                'title' => 'Update dependencies',
            ]);

        $mock->shouldReceive('createBranch')
            ->with('Update dependencies', 7)
            ->once()
            ->andReturn('housekeeping/7-update-dependencies');

        $mock->shouldReceive('assignIssue')
            ->with('my-repo', 7)
            ->once();
    });

    $this->artisan('housekeeping:start', ['repo' => 'my-repo', 'issue' => 7])
        ->expectsOutputToContain('housekeeping/7-update-dependencies')
        ->expectsOutputToContain('Issue #7 assigned to you')
        ->assertSuccessful();
});

it('handles git failure gracefully when starting work', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->with('my-repo', 7)
            ->once()
            ->andReturn([
                'number' => 7,
                'title' => 'Update dependencies',
            ]);

        $mock->shouldReceive('createBranch')
            ->with('Update dependencies', 7)
            ->once()
            ->andThrow(new \RuntimeException('branch already exists'));
    });

    $this->artisan('housekeeping:start', ['repo' => 'my-repo', 'issue' => 7])
        ->expectsOutputToContain('Git operation failed')
        ->assertFailed();
});

it('exports an issue non-interactively with the output option', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->with('my-repo', 3)
            ->once()
            ->andReturn([
                'number' => 3,
                'title' => 'Add dark mode',
                'body' => 'Users want dark mode.',
                'user' => ['login' => 'gordon'],
                'assignees' => [],
                'labels' => [],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-05T00:00:00Z',
            ]);

        $mock->shouldReceive('getComments')
            ->with('my-repo', 3)
            ->once()
            ->andReturn([]);
    });

    $path = sys_get_temp_dir().'/housekeeping-test-export-output.json';

    $this->artisan('housekeeping:export', ['repo' => 'my-repo', 'issue' => 3, '--output' => $path])
        ->expectsOutputToContain("Saved to {$path}")
        ->assertSuccessful();

    $json = json_decode(file_get_contents($path), true);

    expect($json['title'])->toBe('Add dark mode');
    expect($json['author'])->toBe('gordon');

    unlink($path);
});

it('exports all open issues to a file', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getAllOpenIssues')
            ->with('my-repo')
            ->once()
            ->andReturn([
                [
                    'number' => 1,
                    'title' => 'First issue',
                    'body' => 'Description one.',
                    'user' => ['login' => 'gordon'],
                    'assignees' => [],
                    'labels' => [['name' => 'bug']],
                    'created_at' => '2026-01-01T00:00:00Z',
                    'updated_at' => '2026-01-02T00:00:00Z',
                ],
                [
                    'number' => 2,
                    'title' => 'Second issue',
                    'body' => 'Description two.',
                    'user' => ['login' => 'gordon'],
                    'assignees' => [],
                    'labels' => [],
                    'created_at' => '2026-01-03T00:00:00Z',
                    'updated_at' => '2026-01-04T00:00:00Z',
                ],
            ]);

        $mock->shouldReceive('getComments')
            ->with('my-repo', 1)
            ->once()
            ->andReturn([
                ['user' => ['login' => 'reviewer'], 'body' => 'Looks like a bug.', 'created_at' => '2026-01-02T00:00:00Z'],
            ]);

        $mock->shouldReceive('getComments')
            ->with('my-repo', 2)
            ->once()
            ->andReturn([]);
    });

    $path = sys_get_temp_dir().'/housekeeping-test-export-all.json';

    $this->artisan('housekeeping:export-all', ['repo' => 'my-repo', '--output' => $path])
        ->expectsOutputToContain('Exported 2 issues')
        ->assertSuccessful();

    $json = json_decode(file_get_contents($path), true);

    expect($json)->toHaveCount(2);
    expect($json[0]['title'])->toBe('First issue');
    expect($json[0]['comments'][0]['body'])->toBe('Looks like a bug.');
    expect($json[1]['title'])->toBe('Second issue');
    expect($json[1]['comments'])->toBeEmpty();

    unlink($path);
});

it('handles no open issues gracefully in export-all', function () {
    $this->mock(Housekeeping::class, function (MockInterface $mock) {
        $mock->shouldReceive('getAllOpenIssues')
            ->with('my-repo')
            ->once()
            ->andReturn([]);
    });

    $this->artisan('housekeeping:export-all', ['repo' => 'my-repo'])
        ->expectsOutputToContain('No open issues found')
        ->assertSuccessful();
});
