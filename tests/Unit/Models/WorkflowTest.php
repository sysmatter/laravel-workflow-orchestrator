<?php

use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\Models\WorkflowAction;

it('has proper relationships', function () {
    $workflow = Workflow::factory()->create();
    WorkflowAction::factory()->count(3)->create([
        'workflow_id' => $workflow->id,
    ]);

    expect($workflow->actions)->toHaveCount(3)
        ->and($workflow->actions->first())->toBeInstanceOf(WorkflowAction::class);
});

it('can update context', function () {
    $workflow = Workflow::factory()->create([
        'context' => ['initial' => 'value'],
    ]);

    $workflow->updateContext(['new' => 'data']);

    expect($workflow->context)
        ->toHaveKey('initial', 'value')
        ->toHaveKey('new', 'data');
});

it('can get current and next actions', function () {
    $workflow = Workflow::factory()->create(['current_action_index' => 1]);

    $action1 = WorkflowAction::factory()->create([
        'workflow_id' => $workflow->id,
        'index' => 0,
    ]);

    $action2 = WorkflowAction::factory()->create([
        'workflow_id' => $workflow->id,
        'index' => 1,
    ]);

    $action3 = WorkflowAction::factory()->create([
        'workflow_id' => $workflow->id,
        'index' => 2,
    ]);

    expect($workflow->getCurrentAction()->id)->toBe($action2->id);
    expect($workflow->getNextAction()->id)->toBe($action3->id);
});
