<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use SysMatter\StatusMachina\StatusMachina;
use SysMatter\WorkflowOrchestrator\Console\Commands\WorkflowCancel;
use SysMatter\WorkflowOrchestrator\Console\Commands\WorkflowRetry;
use SysMatter\WorkflowOrchestrator\Console\Commands\WorkflowStatus;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\States\WorkflowStateConfig;

class WorkflowOrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow-orchestrator.php', 'workflow-orchestrator');

        $this->app->singleton(WorkflowRegistry::class);
        $this->app->singleton(WorkflowMachine::class);

        $this->app->singleton('workflow-machine', fn ($app) => $app->make(WorkflowMachine::class));
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        StatusMachina::registerStateConfig('workflow', WorkflowStateConfig::class);
        StatusMachina::registerStateManagement(Workflow::class, 'status', 'workflow');

        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/workflow-orchestrator.php' => config_path('workflow-orchestrator.php'),
            ], 'workflow-machine-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'workflow-machine-migrations');

            // Register commands
            $this->commands([
                WorkflowStatus::class,
                WorkflowRetry::class,
                WorkflowCancel::class,
            ]);
        }

        // Auto-register workflows from config
        $workflows = config('workflow-orchestrator.workflows', []);
        $registry = $this->app->make(WorkflowRegistry::class);

        foreach ($workflows as $type => $configClass) {
            $registry->register($type, $configClass);
        }
    }

    public function provides(): array
    {
        return ['workflow-orchestrator'];
    }
}
