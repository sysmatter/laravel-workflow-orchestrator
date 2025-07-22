<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions;

use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

class ContextUpdateAction extends AbstractAction
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        $workflow->updateContext([
            'processed_by_action_1' => true,
            'timestamp_1' => now()->toIsoString(),
        ]);

        return ['context_updated' => true];
    }
}
