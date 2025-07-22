<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures;

use SysMatter\WorkflowOrchestrator\WorkflowConfig;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\SimpleAction;

class SimpleWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Simple Test Workflow';
    }

    public function actions(): array
    {
        return $this->sequential([
            SimpleAction::class,
        ]);
    }
}
