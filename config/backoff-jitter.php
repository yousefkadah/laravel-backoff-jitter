<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Set to false to disable jitter everywhere without removing the package.
    | Useful when you want deterministic delays in a test suite.
    |
    */

    'enabled' => env('QUEUE_BACKOFF_JITTER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Ratio
    |--------------------------------------------------------------------------
    |
    | Applied to every job that does not declare its own ratio. Leave this null
    | to keep jitter opt-in, so only jobs carrying the BackoffJitter attribute
    | or a $backoffJitter property are affected.
    |
    | A ratio of 0.25 means a 60 second backoff becomes 45 to 75 seconds.
    |
    */

    'default_ratio' => env('QUEUE_BACKOFF_JITTER_RATIO'),

    /*
    |--------------------------------------------------------------------------
    | Unbounded Attempts
    |--------------------------------------------------------------------------
    |
    | Jitter is calculated per attempt when the job is dispatched. Jobs with no
    | maximum attempts have no natural limit, so this caps how many distinct
    | delays are generated. Later attempts reuse the final delay, matching how
    | the queue worker already handles a short backoff list.
    |
    */

    'unbounded_attempts' => 10,

];
