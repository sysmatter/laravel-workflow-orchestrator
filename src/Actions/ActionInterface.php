<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Actions;

use SysMatter\WorkflowOrchestrator\Models\Workflow;

interface ActionInterface
{
    public function execute(Workflow $workflow, array $context = []): mixed;
}
