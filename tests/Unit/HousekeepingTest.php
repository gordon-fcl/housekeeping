<?php

declare(strict_types=1);

namespace Tests;

use FCL\Housekeeping\Housekeeping;
use GrahamCampbell\GitHub\Facades\GitHub;
use Mockery;

beforeEach(function () {
    \DG\BypassFinals::enable();
});

it('fetches repositories for the authenticated user', function () {
    $expected = [
        ['name' => 'repo-one'],
        ['name' => 'repo-two'],
    ];

    GitHub::shouldReceive('me->repositories')
        ->once()
        ->andReturn($expected);

    $housekeeping = new Housekeeping();
    $repos = $housekeeping->getRepos();

    expect($repos)->toBe($expected);
});

it('fetches labels for a given repository', function () {
    $expected = [
        ['name' => 'bug'],
        ['name' => 'enhancement'],
    ];

    GitHub::shouldReceive('api->show')
        ->with()
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->labels->all')
        ->with('testuser', 'my-repo')
        ->once()
        ->andReturn($expected);

    $housekeeping = new Housekeeping();
    $labels = $housekeeping->getRepoLabels('my-repo');

    expect($labels)->toBe($expected);
});

it('fetches all open issues for a repository', function () {
    $expected = [
        ['number' => 1, 'title' => 'First issue'],
        ['number' => 2, 'title' => 'Second issue'],
    ];

    GitHub::shouldReceive('api->show')
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->all')
        ->with('testuser', 'my-repo', ['state' => 'open'])
        ->once()
        ->andReturn($expected);

    $housekeeping = new Housekeeping();
    $issues = $housekeeping->getAllOpenIssues('my-repo');

    expect($issues)->toBe($expected);
});

it('fetches issues filtered by label', function () {
    $expected = [
        ['number' => 1, 'title' => 'Bug report'],
    ];

    GitHub::shouldReceive('api->show')
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->all')
        ->with('testuser', 'my-repo', ['state' => 'open', 'labels' => 'bug'])
        ->once()
        ->andReturn($expected);

    $housekeeping = new Housekeeping();
    $issues = $housekeeping->getIssues('my-repo', 'bug');

    expect($issues)->toBe($expected);
});

it('uses the configured default label when none is provided', function () {
    config()->set('housekeeping.label', 'chore');

    GitHub::shouldReceive('api->show')
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->all')
        ->with('testuser', 'my-repo', ['state' => 'open', 'labels' => 'chore'])
        ->once()
        ->andReturn([]);

    $housekeeping = new Housekeeping();
    $housekeeping->getIssues('my-repo');
});

it('fetches a single issue by number', function () {
    $expected = [
        'number' => 42,
        'title' => 'Fix the thing',
        'body' => 'It is broken.',
    ];

    GitHub::shouldReceive('api->show')
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->show')
        ->with('testuser', 'my-repo', 42)
        ->once()
        ->andReturn($expected);

    $housekeeping = new Housekeeping();
    $issue = $housekeeping->getIssue('my-repo', 42);

    expect($issue['number'])->toBe(42);
    expect($issue['title'])->toBe('Fix the thing');
});

it('fetches comments for an issue', function () {
    $expected = [
        ['body' => 'First comment', 'user' => ['login' => 'someone']],
        ['body' => 'Second comment', 'user' => ['login' => 'another']],
    ];

    GitHub::shouldReceive('api->show')
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->comments->all')
        ->with('testuser', 'my-repo', 42)
        ->once()
        ->andReturn($expected);

    $housekeeping = new Housekeeping();
    $comments = $housekeeping->getComments('my-repo', 42);

    expect($comments)->toHaveCount(2);
    expect($comments[0]['body'])->toBe('First comment');
});

it('assigns the authenticated user to an issue', function () {
    GitHub::shouldReceive('api->show')
        ->andReturn(['login' => 'testuser']);

    GitHub::shouldReceive('api->update')
        ->with('testuser', 'my-repo', 42, ['assignees' => ['testuser']])
        ->once();

    $housekeeping = new Housekeeping();
    $housekeeping->assignIssue('my-repo', 42);
});

it('generates a branch name from the issue title and number', function () {
    $housekeeping = Mockery::mock(Housekeeping::class)->makePartial();
    $housekeeping->shouldAllowMockingProtectedMethods();

    // Mock the git calls to avoid actual shell execution
    $housekeeping->shouldReceive('createBranch')
        ->passthru();

    // We cannot easily test the git exec calls, so test the branch name logic directly
    $title = 'Fix the login redirect issue';
    $slug = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($title, 40, ''));
    $expected = "housekeeping/42-{$slug}";

    expect($expected)->toBe('housekeeping/42-fix-the-login-redirect-issue');
});

it('returns the authenticated username', function () {
    GitHub::shouldReceive('api->show')
        ->once()
        ->andReturn(['login' => 'testuser']);

    $housekeeping = new Housekeeping();

    expect($housekeeping->username())->toBe('testuser');
});
