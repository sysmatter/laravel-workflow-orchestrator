<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions;

use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

class FinalAction extends AbstractAction
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        $workflow->updateContext([
            'processed_by_action_2' => true,
            'completed' => true,
        ]);

        return ['workflow_completed' => true];
    }
}
