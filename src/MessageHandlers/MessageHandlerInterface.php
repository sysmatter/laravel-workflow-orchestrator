<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\MessageHandlers;

interface MessageHandlerInterface
{
    public function handle(array $message): void;

    public function supports(string $topic, array $message): bool;
}
