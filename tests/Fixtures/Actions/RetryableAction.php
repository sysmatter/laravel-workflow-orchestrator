<?php

namespace SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions;

use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Attributes\Retry;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

#[Retry(maxAttempts: 5, delay: 10, backoff: true)]
class RetryableAction extends AbstractAction
{
    public static int $attempts = 0;
    public static int $failUntilAttempt = 0;

    protected array $retryableExceptions = [
        ConnectionException::class,
    ];

    protected array $nonRetryableExceptions = [
        InvalidArgumentException::class,
    ];

    /**
     * @throws ConnectionException
     */
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        self::$attempts++;

        if ($context['throw_non_retryable'] ?? false) {
            throw new InvalidArgumentException('Non-retryable error');
        }

        if (self::$attempts < self::$failUntilAttempt) {
            throw new ConnectionException('Network error');
        }

        return ['attempts' => self::$attempts];
    }
}
