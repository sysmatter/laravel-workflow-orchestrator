<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Retry
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $delay = 5, // seconds
        public bool $backoff = true, // exponential backoff
    ) {
    }
}
