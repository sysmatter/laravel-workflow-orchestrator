<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Database\Factories;

use SysMatter\WorkflowOrchestrator\Models\Workflow;
use SysMatter\WorkflowOrchestrator\States\WorkflowState;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition()
    {
        return [
            'workflow_id' => $this->faker->uuid(),
            'correlation_id' => $this->faker->uuid(),
            'type' => $this->faker->randomElement(['order', 'user', 'data']),
            'status' => WorkflowState::CREATED,
            'context' => [],
            'current_action_index' => 0,
            'triggered_by' => 'system',
            'trigger_type' => 'test',
        ];
    }

    public function processing()
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowState::PROCESSING,
        ]);
    }

    public function completed()
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowState::COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
