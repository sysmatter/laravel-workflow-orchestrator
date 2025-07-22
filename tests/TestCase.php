<?php

namespace SysMatter\WorkflowOrchestrator\Tests;

use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Spatie\Activitylog\Models\Activity;
use SysMatter\WorkflowOrchestrator\WorkflowOrchestratorServiceProvider;
use SysMatter\StatusMachina\StatusMachinaServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    #[Override] protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        //        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        //         Run activity log migration
        $this->artisan('migrate', [
            '--path' => __DIR__ . '/database/migrations/create_activity_log_table.php',
            '--realpath' => true,
        ])->run();
        $this->artisan('migrate', [
            '--path' => __DIR__ . '/database/migrations/add_event_column_to_activity_log_table.php',
            '--realpath' => true,
        ])->run();
        $this->artisan('migrate', [
            '--path' => __DIR__ . '/database/migrations/add_batch_uuid_column_to_activity_log_table.php',
            '--realpath' => true,
        ])->run();

        Model::setEventDispatcher($this->app['events']);

        // Or specifically for activity logging
        Activity::macro('testMode', function () {
            return $this;
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            WorkflowOrchestratorServiceProvider::class,
            StatusMachinaServiceProvider::class,
            ActivitylogServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup queue
        //        $app['config']->set('queue.default', 'sync');

        // Setup activity log - but don't enable by default
        $app['config']->set('activitylog.default_log_name', 'workflow');
        $app['config']->set('activitylog.enabled', true);
        $app['config']->set('activitylog.table_name', 'activity_log');
        $app['config']->set('activitylog.activity_model', Activity::class);
        $app['config']->set('activitylog.database_connection', 'testing');

        // Set the workflow orchestrator to enable logging
        $app['config']->set('workflow-orchestrator.activity_log.enabled', true);
    }
}
