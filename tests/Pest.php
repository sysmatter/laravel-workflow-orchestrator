<?php

use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Global helper functions
function createWorkflow(string $type = 'test', array $context = []): Workflow
{
    return WorkflowMachine::startWorkflow($type, $context);
}
