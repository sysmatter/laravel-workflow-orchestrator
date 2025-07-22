<?php

// tests/Feature/WorkflowQueueTest.php

use Illuminate\Support\Facades\Queue;
use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;
use SysMatter\WorkflowOrchestrator\Jobs\WorkflowActionJob;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\CustomQueueWorkflow;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\QueuedWorkflow;
use SysMatter\WorkflowOrchestrator\WorkflowRegistry;

beforeEach(function () {
    app(WorkflowRegistry::class)
        ->register('queued-test', QueuedWorkflow::class);
});

it('dispatches queued actions as jobs', function () {
    // Use a non-sync queue for this test
    config(['queue.default' => 'database']);
    Queue::fake();

    $workflow = WorkflowMachine::startWorkflow('queued-test', [
        'test' => true,
    ]);

    Queue::assertPushed(WorkflowActionJob::class, function ($job) use ($workflow) {
        return $job->workflowId === $workflow->id;
    });

    expect($workflow->fresh()->stateIs('waiting'))->toBeTrue();
});

it('can process queued actions', function () {
    // Don't fake queue, use real sync driver
    config(['queue.default' => 'sync']);

    $workflow = WorkflowMachine::startWorkflow('queued-test', [
        'process_sync' => true,
    ]);

    $workflow->refresh();

    // With sync queue, it should complete
    expect($workflow->stateIs('completed'))->toBeTrue();
    expect($workflow->actions()->first()->completed_at)->not->toBeNull();
});

it('dispatches to correct queue', function () {
    // Use a non-sync queue for this test
    config(['queue.default' => 'database']);
    Queue::fake();

    // Register the custom queue workflow
    app(WorkflowRegistry::class)
        ->register('custom-queue-test', CustomQueueWorkflow::class);

    $workflow = WorkflowMachine::startWorkflow('custom-queue-test', []);

    // We're pushing WorkflowActionJob, not the action itself
    Queue::assertPushedOn('reports', WorkflowActionJob::class);
});
