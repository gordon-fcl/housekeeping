<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Label
    |--------------------------------------------------------------------------
    |
    | The default GitHub issue label to filter by when running housekeeping
    | commands. This can be overridden with the --tag option.
    |
    */

    'label' => env('HOUSEKEEPING_LABEL', 'housekeeping'),

    /*
    |--------------------------------------------------------------------------
    | Default Branch
    |--------------------------------------------------------------------------
    |
    | The branch to create new working branches from when starting work
    | on an issue.
    |
    */

    'base_branch' => env('HOUSEKEEPING_BASE_BRANCH', 'staging'),

    /*
    |--------------------------------------------------------------------------
    | GitHub Connection
    |--------------------------------------------------------------------------
    |
    | Configure which graham-campbell/github connection to use. The token
    | value should be set via your .env file, never committed to source.
    |
    | See: https://github.com/GrahamCampbell/Laravel-GitHub
    |
    */

    'github' => [
        'connection' => env('HOUSEKEEPING_GITHUB_CONNECTION', 'main'),
    ],

];
