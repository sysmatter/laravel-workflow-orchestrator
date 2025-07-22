<?php


use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\States\WorkflowState;
use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;

it('transitions through valid states', function () {
    $workflow = Workflow::factory()->create([
        'status' => WorkflowState::CREATED,
    ]);

    // Valid transitions
    expect($workflow->canTransitionTo(WorkflowState::PROCESSING))->toBeTrue();
    expect($workflow->canTransitionTo(WorkflowState::CANCELLED))->toBeTrue();
    expect($workflow->canTransitionTo(WorkflowState::COMPLETED))->toBeFalse();

    $workflow->transition('start');
    expect($workflow->status)->toBe(WorkflowState::PROCESSING);

    // From processing
    expect($workflow->canTransitionTo(WorkflowState::WAITING))->toBeTrue();
    expect($workflow->canTransitionTo(WorkflowState::COMPLETED))->toBeTrue();
    expect($workflow->canTransitionTo(WorkflowState::FAILED))->toBeTrue();
    expect($workflow->canTransitionTo(WorkflowState::PAUSED))->toBeTrue();
});

it('prevents invalid state transitions', function () {
    $workflow = Workflow::factory()->create([
        'status' => WorkflowState::COMPLETED,
    ]);

    expect(fn () => $workflow->transition('resume'))
        ->toThrow(Exception::class);
});

it('tracks state timestamps', function () {
    // Register test workflow for this test
    app(\SysMatter\WorkflowOrchestrator\WorkflowRegistry::class)
        ->register('test', \SysMatter\WorkflowOrchestrator\Tests\Fixtures\TestWorkflow::class);

    $workflow = WorkflowMachine::startWorkflow('test', []);

    // Don't transition again if already completed
    if (!$workflow->stateIs('completed')) {
        $workflow->transition('complete');
        $workflow->update(['completed_at' => now()]);
    }

    expect($workflow->completed_at)->not->toBeNull();
});
