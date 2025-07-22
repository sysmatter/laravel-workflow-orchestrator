<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use InvalidArgumentException;
use RuntimeException;
use SysMatter\WorkflowOrchestrator\Actions\ActionInterface;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\Models\WorkflowAction;
use SysMatter\WorkflowOrchestrator\Events\WorkflowStarted;
use SysMatter\WorkflowOrchestrator\Events\WorkflowCompleted;
use SysMatter\WorkflowOrchestrator\Events\ActionStarted;
use SysMatter\WorkflowOrchestrator\Events\ActionCompleted;
use SysMatter\WorkflowOrchestrator\Events\ActionFailed;
use SysMatter\WorkflowOrchestrator\States\WorkflowState;
use SysMatter\WorkflowOrchestrator\Actions\Contracts\WaitsForResponse;
use SysMatter\WorkflowOrchestrator\Jobs\WorkflowActionJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Fork\Fork;
use Throwable;

class WorkflowMachine
{
    private ?Workflow $currentWorkflow = null;

    public function __construct(
        private WorkflowRegistry $registry
    ) {
    }

    public function startWorkflow(string $type, ?array $context = null, array $options = []): Workflow
    {
        $context ??= [];
        $config = $this->registry->getConfig($type);

        if (!$config) {
            throw new InvalidArgumentException("Unknown workflow type: {$type}");
        }

        return DB::transaction(function () use ($context, $type, $options, $config) {
            $workflow = Workflow::create([
                'workflow_id' => (string) Str::uuid(),
                'correlation_id' => $context['correlation_id'] ?? (string) Str::uuid(),
                'type' => $type,
                'context' => $context,
                'triggered_by' => $options['triggered_by'] ?? null, // e.g., 'user:123', 'system', 'api'
                'trigger_type' => $options['trigger_type'] ?? null, // e.g., 'manual', 'scheduled', 'webhook'
                'status' => WorkflowState::CREATED,
                'current_action_index' => 0,
            ]);

            // Create action records
            foreach ($config->actions() as $actionConfig) {
                $workflow->actions()->create([
                    'action_id' => (string) Str::uuid(),
                    'class' => $actionConfig['class'],
                    'index' => $actionConfig['index'],
                    'group_index' => $actionConfig['group_index'],
                    'is_concurrent' => $actionConfig['is_concurrent'],
                    'context' => $context,
                    'max_retries' => $this->getActionMaxRetries($actionConfig['class']),
                ]);
            }

            event(new WorkflowStarted($workflow));

            // Start processing
            $this->process($workflow);

            return $workflow;
        });
    }

    public function for($workflowOrId): self
    {
        $this->currentWorkflow = is_string($workflowOrId)
            ? Workflow::where('workflow_id', $workflowOrId)->firstOrFail()
            : $workflowOrId;

        return $this;
    }

    public function getStatus(): array
    {
        if (!$this->currentWorkflow) {
            throw new RuntimeException('No workflow selected');
        }

        return [
            'workflow_id' => $this->currentWorkflow->workflow_id,
            'type' => $this->currentWorkflow->type,
            'status' => $this->currentWorkflow->status,
            'context' => $this->currentWorkflow->context,
            'current_action' => $this->currentWorkflow->getCurrentAction()?->class,
            'completed_actions' => $this->currentWorkflow->actions()
                ->whereNotNull('completed_at')
                ->count(),
            'total_actions' => $this->currentWorkflow->actions()->count(),
            'created_at' => $this->currentWorkflow->created_at,
            'updated_at' => $this->currentWorkflow->updated_at,
        ];
    }

    public function process(?Workflow $workflow = null): void
    {
        $workflow = $workflow ?? $this->currentWorkflow;

        if (!$workflow) {
            throw new RuntimeException('No workflow to process');
        }

        $this->processWorkflow($workflow);
    }

    public function resume(): void
    {
        if (!$this->currentWorkflow) {
            throw new RuntimeException('No workflow selected');
        }

        if ($this->currentWorkflow->status === WorkflowState::PAUSED) {
            $this->currentWorkflow->transition('resume');
            $this->currentWorkflow->update(['paused_at' => null]);
            $this->currentWorkflow->save();
            $this->process($this->currentWorkflow);
        }
    }

    public function cancel(): void
    {
        if (!$this->currentWorkflow) {
            throw new RuntimeException('No workflow selected');
        }

        $this->currentWorkflow->transition('cancel');
        $this->currentWorkflow->save();
    }

    private function processWorkflow(Workflow $workflow): void
    {
        // First transition from created to processing
        if ($workflow->stateIs('created')) {
            $workflow->transition('start');
            $workflow->save();
        }

        // If we're waiting, transition back to processing
        if ($workflow->stateIs('waiting')) {
            $workflow->transition('resume');
            $workflow->save();
        }

        // Check if we can continue processing
        if (!$workflow->stateIs('processing')) {
            return;
        }

        // Get next actions to process
        $nextActions = $this->getNextActions($workflow);

        if ($nextActions->isEmpty()) {
            // No more actions, workflow is complete
            $workflow->transition('complete');
            $workflow->update(['completed_at' => now()]);
            $workflow->save();
            event(new WorkflowCompleted($workflow));
            return;
        }

        // Check if actions are concurrent
        /** @var WorkflowAction $firstAction */
        $firstAction = $nextActions->first();
        $groupIndex = $firstAction->group_index;
        $concurrentActions = $nextActions->filter(fn (WorkflowAction $a) => $a->group_index === $groupIndex);

        if ($concurrentActions->count() > 1 && $firstAction->is_concurrent) {
            $this->processConcurrentActions($workflow, $concurrentActions);
        } else {
            $this->processSequentialAction($workflow, $firstAction);
        }
    }

    private function processConcurrentActions(Workflow $workflow, $actions): void
    {
        $tasks = [];

        foreach ($actions as $action) {
            $tasks[] = function () use ($workflow, $action) {
                return $this->executeAction($workflow, $action);
            };
        }

        // Use spatie/fork for concurrent execution
        $results = Fork::new()->run(...$tasks);

        // Update workflow context with results
        $allCompleted = true;
        foreach ($actions as $index => $action) {
            if (!$results[$index]['success']) {
                $allCompleted = false;
            }
        }

        if ($allCompleted) {
            $workflow->update(['current_action_index' => $actions->last()->index + 1]);
            $this->processWorkflow($workflow);
        }
    }

    private function processSequentialAction(Workflow $workflow, WorkflowAction $action): void
    {
        $result = $this->executeAction($workflow, $action);

        if ($result['success']) {
            $workflow->update(['current_action_index' => $action->index + 1]);

            // Continue processing unless we're waiting
            if ($workflow->status !== WorkflowState::WAITING) {
                $this->processWorkflow($workflow);
            }
        }
    }

    private function executeAction(Workflow $workflow, WorkflowAction $action): array
    {
        try {
            $action->update(['started_at' => now()]);
            event(new ActionStarted($workflow, $action));

            $actionInstance = app($action->class);

            // Check if action should be queued
            if ($actionInstance instanceof ShouldQueue && $actionInstance instanceof ActionInterface) {
                // For sync queue, execute directly
                if (config('queue.default') === 'sync') {
                    // Execute the action directly
                    $result = $actionInstance->execute($workflow, $action->context ?? []);

                    $action->update([
                        'completed_at' => now(),
                        'result' => is_array($result) ? $result : ['value' => $result],
                    ]);

                    event(new ActionCompleted($workflow, $action));

                    return ['success' => true, 'result' => $result];
                } else {
                    // Queue the action
                    $this->queueAction($workflow, $action);
                    return ['success' => true, 'queued' => true];
                }
            }

            // Execute action synchronously
            $result = $actionInstance->execute($workflow, $action->context ?? []);

            // Check if action waits for response
            if ($actionInstance instanceof WaitsForResponse) {
                $this->handleWaitingAction($workflow, $action, $actionInstance);
                return ['success' => true, 'waiting' => true];
            }

            // Action completed successfully
            $action->update([
                'completed_at' => now(),
                'result' => is_array($result) ? $result : ['value' => $result],
            ]);

            event(new ActionCompleted($workflow, $action));

            return ['success' => true, 'result' => $result];

        } catch (Throwable $e) {
            return $this->handleActionFailure($workflow, $action, $e);
        }
    }

    private function queueAction(Workflow $workflow, WorkflowAction $action): void
    {
        $actionInstance = app($action->class);
        $job = new WorkflowActionJob($workflow->id, $action->id);

        // Check if a custom queue is specified
        if ($actionInstance->queue) {
            $job->onQueue($actionInstance->queue);
        }

        dispatch($job);

        // Only transition to waiting if not using sync queue
        if (config('queue.default') !== 'sync') {
            $workflow->transition('wait');
            $workflow->save();
        }
    }

    private function handleWaitingAction(Workflow $workflow, WorkflowAction $action, WaitsForResponse $actionInstance): void
    {
        $workflow->transition('wait');
        $workflow->save();

        // Schedule timeout check
        dispatch(function () use ($action, $actionInstance, $workflow) {
            if (!$action->fresh()->isComplete()) {
                if ($timeoutAction = $actionInstance->getTimeoutAction()) {
                    // This would need to be implemented if needed
                    Log::warning('Timeout action insertion not implemented', [
                        'workflow_id' => $workflow->workflow_id,
                        'action' => $timeoutAction,
                    ]);
                } else {
                    // Mark action as failed due to timeout
                    $action->update([
                        'failed_at' => now(),
                        'exception' => ['message' => 'Action timed out'],
                    ]);
                    $this->processWorkflow($workflow);
                }
            }
        })->delay(now()->addSeconds($actionInstance->getWaitDuration()));
    }

    public function handleActionFailure(Workflow $workflow, WorkflowAction $action, Throwable $e): array
    {
        Log::error('Workflow action failed', [
            'workflow_id' => $workflow->workflow_id,
            'action' => $action->class,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $actionInstance = app($action->class);

        // Only increment retry count if we're actually going to retry
        if ($actionInstance->shouldRetry($e)) {
            $action->increment('retry_count');

            // Check if we can still retry
            if ($action->canRetry()) {
                $action->update([
                    'failed_at' => now(),
                    'exception' => [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ]);

                // Schedule retry with exponential backoff
                dispatch(function () use ($workflow, $action) {
                    $action->update(['failed_at' => null]);
                    $this->processSequentialAction($workflow, $action);
                })->delay(now()->addSeconds($actionInstance->getRetryDelay($action->retry_count)));

                return ['success' => false, 'retry_scheduled' => true];
            }
        }

        // Don't retry - either non-retryable or max retries reached
        $action->update([
            'failed_at' => now(),
            'exception' => [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ],
        ]);

        event(new ActionFailed($workflow, $action));

        // Pause workflow for manual intervention
        if ($workflow->canTransitionTo('paused')) {
            $workflow->transition('pause');
            $workflow->update(['paused_at' => now()]);
            $workflow->save();
        }

        return ['success' => false, 'paused' => true];
    }

    private function getNextActions(Workflow $workflow)
    {
        return $workflow->actions()
            ->where('index', '>=', $workflow->current_action_index)
            ->whereNull('completed_at')
            ->orderBy('index')
            ->get();
    }

    private function getActionMaxRetries(string $actionClass): int
    {
        try {
            $actionInstance = app($actionClass);

            if (is_object($actionInstance) && method_exists($actionInstance, 'getMaxRetries')) {
                return $actionInstance->getMaxRetries();
            }

            return 3; // default
        } catch (Exception $e) {
            return 3; // default if instantiation fails
        }
    }
}
