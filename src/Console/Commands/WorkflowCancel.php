<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use SysMatter\WorkflowOrchestrator\WorkflowMachine;

class WorkflowCancel extends Command
{
    protected $signature = 'workflow:cancel {workflow_id : The workflow ID}';

    protected $description = 'Cancel a running workflow';

    public function handle(WorkflowMachine $machine): int
    {
        $workflowId = $this->argument('workflow_id');

        if (! $this->confirm("Are you sure you want to cancel workflow {$workflowId}?")) {
            return Command::SUCCESS;
        }

        try {
            $machine->for($workflowId)->cancel();
            $this->info("Workflow {$workflowId} has been cancelled.");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to cancel workflow: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
