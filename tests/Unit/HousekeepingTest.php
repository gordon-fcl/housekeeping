<?php

declare(strict_types=1);

namespace Tests;

use DG\BypassFinals;
use FCL\Housekeeping\Housekeeping;
use GrahamCampbell\GitHub\Facades\GitHub;
use Illuminate\Support\Str;
use Mockery;

beforeEach(function () {
    BypassFinals::enable();
});

function mockUsername(string $login = 'testuser'): void
{
    $currentUserApi = Mockery::mock();
    $currentUserApi->shouldReceive('show')->andReturn(['login' => $login]);

    GitHub::shouldReceive('api')
        ->with('currentUser')
        ->andReturn($currentUserApi);
}

it('fetches repositories for the authenticated user', function () {
    $expected = [
        ['name' => 'repo-one'],
        ['name' => 'repo-two'],
    ];

    GitHub::shouldReceive('me->repositories')
        ->once()
        ->andReturn($expected);

    expect((new Housekeeping)->getRepos())->toBe($expected);
});

it('fetches labels for a given repository', function () {
    mockUsername();

    $labelsApi = Mockery::mock();
    $labelsApi->shouldReceive('all')
        ->with('testuser', 'my-repo')
        ->once()
        ->andReturn([['name' => 'bug'], ['name' => 'enhancement']]);

    $repoApi = Mockery::mock();
    $repoApi->shouldReceive('labels')->andReturn($labelsApi);

    GitHub::shouldReceive('api')
        ->with('repo')
        ->andReturn($repoApi);

    expect((new Housekeeping)->getRepoLabels('my-repo'))->toHaveCount(2);
});

it('fetches all open issues for a repository', function () {
    mockUsername();

    $issuesApi = Mockery::mock();
    $issuesApi->shouldReceive('all')
        ->with('testuser', 'my-repo', ['state' => 'open'])
        ->once()
        ->andReturn([['number' => 1], ['number' => 2]]);

    GitHub::shouldReceive('api')
        ->with('issues')
        ->andReturn($issuesApi);

    expect((new Housekeeping)->getAllOpenIssues('my-repo'))->toHaveCount(2);
});

it('fetches issues filtered by label', function () {
    mockUsername();

    $issuesApi = Mockery::mock();
    $issuesApi->shouldReceive('all')
        ->with('testuser', 'my-repo', ['state' => 'open', 'labels' => 'bug'])
        ->once()
        ->andReturn([['number' => 1, 'title' => 'Bug report']]);

    GitHub::shouldReceive('api')
        ->with('issues')
        ->andReturn($issuesApi);

    expect((new Housekeeping)->getIssues('my-repo', 'bug'))->toHaveCount(1);
});

it('uses the configured default label when none is provided', function () {
    config()->set('housekeeping.label', 'chore');
    mockUsername();

    $issuesApi = Mockery::mock();
    $issuesApi->shouldReceive('all')
        ->with('testuser', 'my-repo', ['state' => 'open', 'labels' => 'chore'])
        ->once()
        ->andReturn([]);

    GitHub::shouldReceive('api')
        ->with('issues')
        ->andReturn($issuesApi);

    (new Housekeeping)->getIssues('my-repo');
});

it('fetches a single issue by number', function () {
    mockUsername();

    $issuesApi = Mockery::mock();
    $issuesApi->shouldReceive('show')
        ->with('testuser', 'my-repo', 42)
        ->once()
        ->andReturn(['number' => 42, 'title' => 'Fix the thing']);

    GitHub::shouldReceive('api')
        ->with('issues')
        ->andReturn($issuesApi);

    $issue = (new Housekeeping)->getIssue('my-repo', 42);

    expect($issue['number'])->toBe(42);
    expect($issue['title'])->toBe('Fix the thing');
});

it('fetches comments for an issue', function () {
    mockUsername();

    $commentsApi = Mockery::mock();
    $commentsApi->shouldReceive('all')
        ->with('testuser', 'my-repo', 42)
        ->once()
        ->andReturn([
            ['body' => 'First comment', 'user' => ['login' => 'someone']],
            ['body' => 'Second comment', 'user' => ['login' => 'another']],
        ]);

    $issuesApi = Mockery::mock();
    $issuesApi->shouldReceive('comments')->andReturn($commentsApi);

    GitHub::shouldReceive('api')
        ->with('issues')
        ->andReturn($issuesApi);

    $comments = (new Housekeeping)->getComments('my-repo', 42);

    expect($comments)->toHaveCount(2);
    expect($comments[0]['body'])->toBe('First comment');
});

it('assigns the authenticated user to an issue', function () {
    mockUsername();

    $issuesApi = Mockery::mock();
    $issuesApi->shouldReceive('update')
        ->with('testuser', 'my-repo', 42, ['assignees' => ['testuser']])
        ->once();

    GitHub::shouldReceive('api')
        ->with('issues')
        ->andReturn($issuesApi);

    (new Housekeeping)->assignIssue('my-repo', 42);
});

it('generates the correct branch name format', function () {
    $title = 'Fix the login redirect issue';
    $slug = Str::slug(Str::limit($title, 40, ''));

    expect("housekeeping/42-{$slug}")->toBe('housekeeping/42-fix-the-login-redirect-issue');
});

it('returns the authenticated username', function () {
    mockUsername('gordon');

    expect((new Housekeeping)->username())->toBe('gordon');
});
