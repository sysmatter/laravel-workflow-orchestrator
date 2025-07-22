<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use SysMatter\WorkflowOrchestrator\WorkflowMachine;

class WorkflowStatus extends Command
{
    protected $signature = 'workflow:status {workflow_id : The workflow ID}';

    protected $description = 'Check the status of a workflow';

    public function handle(WorkflowMachine $machine): int
    {
        $workflowId = $this->argument('workflow_id');

        try {
            $status = $machine->for($workflowId)->getStatus();

            $this->info("Workflow Status: {$status['status']}");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Workflow ID', $status['workflow_id']],
                    ['Type', $status['type']],
                    ['Status', $status['status']],
                    ['Current Action', $status['current_action'] ?? 'None'],
                    ['Progress', "{$status['completed_actions']}/{$status['total_actions']}"],
                    ['Created', $status['created_at']],
                    ['Updated', $status['updated_at']],
                ]
            );

            if (! empty($status['context'])) {
                $this->info("\nContext:");
                $this->line(json_encode($status['context'], JSON_PRETTY_PRINT));
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to get workflow status: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
