<?php

use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;
use SysMatter\WorkflowOrchestrator\Models\WorkflowAction;
use SysMatter\WorkflowOrchestrator\States\WorkflowState;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\SimpleAction;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\RetryableWorkflow;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\RetryableAction;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\SimpleWorkflow;
use SysMatter\WorkflowOrchestrator\WorkflowRegistry;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

beforeEach(function () {
    app(WorkflowRegistry::class)
        ->register('retry-test', RetryableWorkflow::class);
});

it('retries failed actions', function () {
    config(['queue.default' => 'sync']);

    // Reset static properties
    RetryableAction::$attempts = 0;
    RetryableAction::$failUntilAttempt = 2;

    $workflow = WorkflowMachine::startWorkflow('retry-test', [
        'should_retry' => true,
    ]);

    // For sync queue, we need to manually process the retry
    // The first attempt should have failed
    $workflow->refresh();
    $action = $workflow->actions()->first();

    // The action should have failed once
    expect($action->retry_count)->toBe(1);

    // Manually trigger the retry processing
    if ($workflow->stateIs('paused')) {
        // Reset the action's failed state
        $action->update(['failed_at' => null]);

        // Resume the workflow
        WorkflowMachine::for($workflow)->resume();

        $workflow->refresh();
    }

    expect($workflow->stateIs('completed'))->toBeTrue();
});

it('pauses workflow after max retries', function () {
    config(['queue.default' => 'sync']);

    // Make action always fail
    RetryableAction::$attempts = 0;
    RetryableAction::$failUntilAttempt = 999;

    $workflow = WorkflowMachine::startWorkflow('retry-test', [
        'always_fail' => true,
    ]);

    $workflow->refresh();
    $action = $workflow->actions->first();

    expect($action->retry_count)->toBe(1); // max retries
    expect($action->failed_at)->not->toBeNull();
    expect($workflow->status)->toBe(WorkflowState::PAUSED);
    expect($workflow->paused_at)->not->toBeNull();
});

it('respects non-retryable exceptions', function () {
    config(['queue.default' => 'sync']);

    $workflow = WorkflowMachine::startWorkflow('retry-test', [
        'throw_non_retryable' => true,
    ]);

    $workflow->refresh();
    $action = $workflow->actions()->first();

    expect($action->retry_count)->toBe(0);
    expect($workflow->status)->toBe(WorkflowState::PAUSED);
});

it('can resume paused workflows', function () {
    // Create a paused workflow with an action
    $workflow = Workflow::factory()->create([
        'status' => 'paused',
        'paused_at' => now(),
        'type' => 'simple',
    ]);

    // Add an uncompleted action
    WorkflowAction::factory()->create([
        'workflow_id' => $workflow->id,
        'class' => SimpleAction::class,
        'index' => 0,
        'group_index' => 0,
    ]);

    // Register the workflow type
    app(WorkflowRegistry::class)
        ->register('simple', SimpleWorkflow::class);

    WorkflowMachine::for($workflow)->resume();

    $fresh = $workflow->fresh();

    // Should be completed since SimpleAction completes immediately
    expect($fresh->stateIs('completed'))->toBeTrue();
    expect($fresh->paused_at)->toBeNull();
});
