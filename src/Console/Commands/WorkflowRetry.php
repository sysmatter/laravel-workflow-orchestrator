<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\WorkflowMachine;

class WorkflowRetry extends Command
{
    protected $signature = 'workflow:retry 
                            {workflow_id : The workflow ID} 
                            {--from-action= : Retry from specific action index}';

    protected $description = 'Retry a failed or paused workflow';

    public function handle(WorkflowMachine $machine): int
    {
        $workflowId = $this->argument('workflow_id');

        try {
            $workflow = Workflow::where('workflow_id', $workflowId)->firstOrFail();

            if (! in_array($workflow->status, ['paused', 'failed'])) {
                $this->error("Workflow is not in a retryable state. Current status: {$workflow->status}");

                return Command::FAILURE;
            }

            if ($fromAction = $this->option('from-action')) {
                $workflow->update(['current_action_index' => (int) $fromAction]);
            }

            $this->info("Retrying workflow {$workflowId}...");
            $machine->for($workflow)->resume();

            $this->info('Workflow resumed successfully!');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to retry workflow: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
