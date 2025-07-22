<?php

use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\SimpleWorkflow;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\MultiActionWorkflow;
use SysMatter\WorkflowOrchestrator\WorkflowRegistry;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Register workflows
    $registry = app(WorkflowRegistry::class);
    $registry->register('simple', SimpleWorkflow::class);
    $registry->register('multi', MultiActionWorkflow::class);
});

it('can start a workflow', function () {
    $workflow = WorkflowMachine::startWorkflow('simple', [
        'user_id' => 123,
        'order_id' => 456,
    ]);

    expect($workflow)
        ->toBeInstanceOf(Workflow::class)
        ->workflow_id->toBeString()
        ->type->toBe('simple')
        ->context->toBe([
            'user_id' => 123,
            'order_id' => 456,
        ])
        ->and($workflow->stateIs('completed'))->toBeTrue();

    // Simple workflow should complete immediately

    assertDatabaseHas('workflows', [
        'workflow_id' => $workflow->workflow_id,
        'type' => 'simple',
    ]);
});

it('can execute sequential actions', function () {
    $workflow = WorkflowMachine::startWorkflow('multi', [
        'value' => 10,
    ]);

    // Refresh to get updated state
    $workflow->refresh();

    expect($workflow->stateIs('completed'))->toBeTrue()
        ->and($workflow->actions)->toHaveCount(3);

    $workflow->actions()->each(function ($action) {
        expect($action->completed_at)->not->toBeNull();
    });
});

it('tracks workflow progress', function () {
    $workflow = WorkflowMachine::startWorkflow('multi', [
        'track_progress' => true,
    ]);

    $status = WorkflowMachine::for($workflow->workflow_id)->getStatus();

    expect($status)
        ->toHaveKeys(['workflow_id', 'type', 'status', 'context', 'current_action', 'completed_actions', 'total_actions'])
        ->workflow_id->toBe($workflow->workflow_id)
        ->type->toBe('multi')
        ->total_actions->toBe(3);
});

it('can handle workflow with context updates', function () {
    $workflow = WorkflowMachine::startWorkflow('multi', [
        'initial_value' => 100,
    ]);

    $workflow->refresh();

    // Actions should have updated the context
    expect($workflow->context)
        ->toHaveKey('initial_value', 100)
        ->toHaveKey('processed_by_action_1')
        ->toHaveKey('processed_by_action_2');
});

it('can be triggered with metadata', function () {
    $workflow = WorkflowMachine::startWorkflow('simple', [
        'data' => 'test',
    ], [
        'triggered_by' => 'user:42',
        'trigger_type' => 'api',
    ]);

    expect($workflow)
        ->triggered_by->toBe('user:42')
        ->trigger_type->toBe('api');
});
