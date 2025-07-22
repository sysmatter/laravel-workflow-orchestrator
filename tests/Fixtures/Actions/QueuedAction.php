<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions;

use Illuminate\Queue\SerializesModels;
use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class QueuedAction extends AbstractAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function execute(Workflow $workflow, array $context = []): array
    {
        return [
            'queued' => true,
            'processed_at' => now()->toIsoString()
        ];
    }
}
