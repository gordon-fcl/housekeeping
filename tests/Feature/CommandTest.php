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
