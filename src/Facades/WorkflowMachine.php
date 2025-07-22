<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Facades;

use Illuminate\Support\Facades\Facade;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

/**
 * @method static Workflow startWorkflow(string $type, array $context = [], array $options = [])
 * @method static \SysMatter\WorkflowOrchestrator\WorkflowMachine for($workflowOrId)
 * @method static array getStatus()
 * @method static void resume()
 * @method static void cancel()
 * @method static void handleActionComplete(string $workflowId, string $actionId, array $result)
 *
 * @see \SysMatter\WorkflowOrchestrator\WorkflowMachine
 */
class WorkflowMachine extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'workflow-machine';
    }
}
