<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures;

use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\CustomQueueAction;
use SysMatter\WorkflowOrchestrator\WorkflowConfig;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\QueuedAction;

class QueuedWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Queued Test Workflow';
    }

    public function actions(): array
    {
        // Use different action based on context
        return $this->sequential([
            QueuedAction::class,
        ]);
    }
}

class CustomQueueWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Custom Queue Workflow';
    }

    public function actions(): array
    {
        return $this->sequential([
            CustomQueueAction::class,
        ]);
    }
}
