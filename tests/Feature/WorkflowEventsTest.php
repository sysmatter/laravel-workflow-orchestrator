<?php


use Illuminate\Support\Facades\Event;
use SysMatter\WorkflowOrchestrator\Events\WorkflowStarted;
use SysMatter\WorkflowOrchestrator\Events\WorkflowCompleted;
use SysMatter\WorkflowOrchestrator\Events\ActionStarted;
use SysMatter\WorkflowOrchestrator\Events\ActionCompleted;
use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\TestWorkflow;
use SysMatter\WorkflowOrchestrator\WorkflowRegistry;

beforeEach(function () {
    Event::fake();

    // Register test workflow
    app(WorkflowRegistry::class)
        ->register('test', TestWorkflow::class);
});

it('dispatches workflow events', function () {
    $workflow = WorkflowMachine::startWorkflow('test', []);

    Event::assertDispatched(WorkflowStarted::class, function ($event) use ($workflow) {
        return $event->workflow->id === $workflow->id;
    });

    // Only complete if not already completed
    if (!$workflow->fresh()->stateIs('completed')) {
        $workflow->transition('complete');
        $workflow->save();
    }

    event(new WorkflowCompleted($workflow));

    Event::assertDispatched(WorkflowCompleted::class);
});

it('dispatches action events', function () {
    $workflow = WorkflowMachine::startWorkflow('test', []);

    Event::assertDispatched(ActionStarted::class);
    Event::assertDispatched(ActionCompleted::class);
});
