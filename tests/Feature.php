<?php

namespace Tests;

use Mockery;
use Mockery\MockInterface;
use FCL\Housekeeping\Housekeeping;
use GrahamCampbell\GitHub\Facades\GitHub;
use GrahamCampbell\GitHub\GitHubManager;

use Github\Client;
use GrahamCampbell\GitHub\Auth\Authenticator\TokenAuthenticator;
use GrahamCampbell\GitHub\Auth\AuthenticatorFactory;


/**
 * I don't need to test that it authenticates, but do need to mock it
 */
it('can fetch all the labels from issues in the current repo', function () {
    \DG\BypassFinals::enable();

    $user = new \StdClass();
    $user->token = 'nice_token';
    $user->username = 'unique_username';

    $repo_name = 'great_repo';

    $client = Mockery::mock(Client::class);

    // Mocking the auth is necessary, otherwise we'll be hitting the GitHub API
    GitHub::shouldReceive('getFactory->make')
        ->once()
        ->with(['token' => $user->token, 'method' => 'token'])
        ->andReturn($client);

    GitHub::getFactory()->make([
        'token'  => $user->token,
        'method' => 'token',
    ]);

    $mock = $this->partialMock(Housekeeping::class, function (MockInterface $mock) use ($repo_name) {
        $mock->shouldReceive('getRepos')
            ->once()
            ->andReturn([$repo_name, 'another_repo'])
            ->shouldReceive('getRepoLabels')
            ->once()
            ->with($repo_name)
            ->andReturn(['labels' => ['bug', 'feature']]);
    });


    $repos = $mock->getRepos();
    $labels = $mock->getRepoLabels($repos[0]);

    expect($labels['labels'])->toBe(['bug', 'feature']);

    return $labels;
});

it('can fetch all the issues in the current repo that match a given label', function ($labels) {
    $repo_name = 'great_repo';
    $mock = $this->partialMock(Housekeeping::class, function (MockInterface $mock) use ($repo_name, $labels) {
        $mock->shouldReceive('getIssues')
            ->with($repo_name, $labels['labels'][0])
            ->once()
            ->andReturn(['issues 1', 'another issue']);
    });
    $issues = $mock->getIssues($repo_name, $labels['labels'][0]);

    expect($issues[0])->toBe('issues 1');
})->depends('it can fetch all the labels from issues in the current repo');

it('can show a specific issue in the current repo', function () {
});

it('can create a branch for a given issue in the current repo', function () {
});
