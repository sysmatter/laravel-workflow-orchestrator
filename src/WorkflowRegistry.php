<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator;

use InvalidArgumentException;

class WorkflowRegistry
{
    private array $workflows = [];

    public function register(string $type, string $configClass): void
    {
        if (! is_subclass_of($configClass, WorkflowConfigInterface::class)) {
            throw new InvalidArgumentException(
                'Workflow config must implement WorkflowConfigInterface'
            );
        }

        $this->workflows[$type] = $configClass;
    }

    public function getConfig(string $type): ?WorkflowConfigInterface
    {
        if (! isset($this->workflows[$type])) {
            return null;
        }

        return app($this->workflows[$type]);
    }

    public function getAll(): array
    {
        return $this->workflows;
    }

    public function has(string $type): bool
    {
        return isset($this->workflows[$type]);
    }
}
