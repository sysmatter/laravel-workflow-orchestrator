<?php

use SysMatter\WorkflowOrchestrator\WorkflowRegistry;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\TestWorkflow;

it('can register workflows', function () {
    $registry = new WorkflowRegistry();

    $registry->register('test', TestWorkflow::class);

    expect($registry->has('test'))->toBeTrue();
    expect($registry->getConfig('test'))->toBeInstanceOf(TestWorkflow::class);
});

it('throws exception for invalid workflow class', function () {
    $registry = new WorkflowRegistry();

    expect(fn () => $registry->register('invalid', \stdClass::class))
        ->toThrow(\InvalidArgumentException::class);
});

it('returns null for unregistered workflow', function () {
    $registry = new WorkflowRegistry();

    expect($registry->getConfig('unknown'))->toBeNull();
});

it('can get all registered workflows', function () {
    $registry = new WorkflowRegistry();

    $registry->register('workflow1', TestWorkflow::class);
    $registry->register('workflow2', TestWorkflow::class);

    expect($registry->getAll())->toHaveCount(2);
});
