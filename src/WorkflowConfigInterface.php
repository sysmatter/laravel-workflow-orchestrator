<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator;

interface WorkflowConfigInterface
{
    public function actions(): array;

    public function name(): string;
}
