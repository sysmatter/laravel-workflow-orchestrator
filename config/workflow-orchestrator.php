<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Configurations
    |--------------------------------------------------------------------------
    |
    | Register your workflow configurations here. The key is the workflow type
    | and the value is the fully qualified class name of your workflow config.
    |
    */
    'workflows' => [
        // 'lexical_import' => \App\Workflows\LexicalImportWorkflow::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 3,
        'delay' => 5, // seconds
        'backoff' => true, // exponential backoff
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */
    'activity_log' => [
        'enabled' => true,
        'log_name' => 'workflow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pub/Sub Configuration
    |--------------------------------------------------------------------------
    */
    'pubsub' => [
        'workflow_actions_topic' => env('WORKFLOW_ACTIONS_TOPIC', 'workflow.actions'),
        'workflow_actions_subscription' => env('WORKFLOW_ACTIONS_SUBSCRIPTION', 'workflow-actions'),
        'enable_streaming' => env('WORKFLOW_PUBSUB_STREAMING', true),
        'max_messages_per_pull' => env('WORKFLOW_MAX_MESSAGES', 100),
    ],
];
