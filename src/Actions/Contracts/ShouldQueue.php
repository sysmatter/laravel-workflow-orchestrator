<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Actions\Contracts;

interface ShouldQueue
{
    public function getTopic(): string;

    public function getPayload(): array;
}
