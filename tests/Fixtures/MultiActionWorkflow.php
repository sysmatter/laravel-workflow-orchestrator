<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures;

use SysMatter\WorkflowOrchestrator\WorkflowConfig;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\SimpleAction;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\ContextUpdateAction;
use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\FinalAction;

class MultiActionWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Multi Action Workflow';
    }

    public function actions(): array
    {
        return $this->sequential([
            SimpleAction::class,
            ContextUpdateAction::class,
            FinalAction::class,
        ]);
    }
}
