<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions;

use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CustomQueueAction extends AbstractAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->queue = 'reports';
    }

    public function execute(Workflow $workflow, array $context = []): array
    {
        return [
            'queued' => true,
            'queue' => 'reports',
            'processed_at' => now()->toIsoString()
        ];
    }
}
