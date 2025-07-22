<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\Models\WorkflowAction;

class ActionCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Workflow $workflow,
        public WorkflowAction $action
    ) {
    }
}
