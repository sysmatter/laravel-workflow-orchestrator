<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Actions;

use ReflectionClass;
use SysMatter\WorkflowOrchestrator\Attributes\Retry;
use Throwable;

abstract class AbstractAction implements ActionInterface
{
    protected array $retryableExceptions = [];

    protected array $nonRetryableExceptions = [];

    public function getMaxRetries(): int
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(Retry::class);

        if (! empty($attributes)) {
            return $attributes[0]->newInstance()->maxAttempts;
        }

        return 3; // default
    }

    public function getRetryDelay(int $attempt): int
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(Retry::class);

        if (! empty($attributes)) {
            $retry = $attributes[0]->newInstance();
            if ($retry->backoff) {
                return $retry->delay * pow(2, $attempt - 1); // exponential backoff
            }

            return $retry->delay;
        }

        return 5 * pow(2, $attempt - 1); // default exponential backoff
    }

    public function shouldRetry(Throwable $exception): bool
    {
        // If non-retryable exceptions are defined, check those first
        if (! empty($this->nonRetryableExceptions)) {
            foreach ($this->nonRetryableExceptions as $nonRetryable) {
                if ($exception instanceof $nonRetryable) {
                    return false;
                }
            }
        }

        // If retryable exceptions are defined, only retry those
        if (! empty($this->retryableExceptions)) {
            foreach ($this->retryableExceptions as $retryable) {
                if ($exception instanceof $retryable) {
                    return true;
                }
            }

            return false;
        }

        // Default: retry all exceptions
        return true;
    }
}
