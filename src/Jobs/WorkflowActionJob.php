<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\Models\WorkflowAction;
use SysMatter\WorkflowOrchestrator\Events\ActionCompleted;
use SysMatter\WorkflowOrchestrator\WorkflowMachine;
use Throwable;

class WorkflowActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // Let the workflow handle retries

    public function __construct(
        public int $workflowId,
        public int $actionId
    ) {
    }

    public function handle(): void
    {
        $workflow = Workflow::findOrFail($this->workflowId);
        $action = WorkflowAction::findOrFail($this->actionId);

        try {
            $actionInstance = app($action->class);
            $result = $actionInstance->execute($workflow, $action->context ?? []);

            $action->update([
                'completed_at' => now(),
                'result' => is_array($result) ? $result : ['value' => $result],
            ]);

            event(new ActionCompleted($workflow, $action));

            // Resume workflow processing
            if ($workflow->stateIs('waiting')) {
                // Update the action index
                $workflow->update(['current_action_index' => $action->index + 1]);

                // Continue processing
                app(WorkflowMachine::class)->for($workflow)->process();
            }
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        $workflow = Workflow::find($this->workflowId);
        $action = WorkflowAction::find($this->actionId);

        if ($workflow && $action) {
            // Let WorkflowMachine handle the failure
            app(WorkflowMachine::class)->handleActionFailure($workflow, $action, $exception);
        }
    }
}
