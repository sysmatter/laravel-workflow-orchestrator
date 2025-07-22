<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Actions\Contracts;

interface WaitsForResponse
{
    public function getWaitDuration(): int; // seconds

    public function getTimeoutAction(): ?string; // class name of action to run on timeout
}
