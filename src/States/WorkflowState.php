<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\States;

use SysMatter\StatusMachina\State\State;

class WorkflowState extends State
{
    public const CREATED = 'created';
    public const PROCESSING = 'processing';
    public const WAITING = 'waiting';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public static function default(): string
    {
        return self::CREATED;
    }

    public static function transitions(): array
    {
        return [
            self::CREATED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::WAITING, self::PAUSED, self::COMPLETED, self::FAILED],
            self::WAITING => [self::PROCESSING, self::PAUSED, self::FAILED],
            self::PAUSED => [self::PROCESSING, self::CANCELLED],
            self::COMPLETED => [], // Terminal state
            self::FAILED => [self::PROCESSING], // Can retry
            self::CANCELLED => [], // Terminal state
        ];
    }
}
