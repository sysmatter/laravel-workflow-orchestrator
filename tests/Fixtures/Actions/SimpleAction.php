<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions;

use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

class SimpleAction extends AbstractAction
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        return ['success' => true, 'timestamp' => now()->toIsoString()];
    }
}
