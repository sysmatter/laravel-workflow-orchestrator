<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures;

use SysMatter\WorkflowOrchestrator\WorkflowConfig;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\SimpleAction;

class TestWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Test Workflow';
    }

    public function actions(): array
    {
        // Just one simple action for basic tests
        return $this->sequential([
            SimpleAction::class,
        ]);
    }
}
