<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Database\Factories;

use SysMatter\WorkflowOrchestrator\Models\WorkflowAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowActionFactory extends Factory
{
    protected $model = WorkflowAction::class;

    public function definition()
    {
        return [
            'workflow_id' => Workflow::factory(),
            'action_id' => $this->faker->uuid(),
            'class' => 'App\\Actions\\TestAction',
            'index' => 0,
            'group_index' => 0,
            'is_concurrent' => false,
            'context' => [],
            'retry_count' => 0,
            'max_retries' => 3,
        ];
    }

    public function completed()
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'result' => ['success' => true],
        ]);
    }
}
