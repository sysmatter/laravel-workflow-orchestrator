<?php

// tests/Fixtures/RetryableWorkflow.php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures;

use SysMatter\WorkflowOrchestrator\WorkflowConfig;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\RetryableAction;

class RetryableWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Retryable Test Workflow';
    }

    public function actions(): array
    {
        return $this->sequential([
            RetryableAction::class,
        ]);
    }
}
