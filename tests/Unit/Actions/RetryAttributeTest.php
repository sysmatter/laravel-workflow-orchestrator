<?php

use SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\RetryableAction;

it('can read retry attributes', function () {
    $action = new RetryableAction();

    expect($action->getMaxRetries())->toBe(5);
    expect($action->getRetryDelay(1))->toBe(10); // First attempt
    expect($action->getRetryDelay(2))->toBe(20); // Second attempt (exponential backoff)
    expect($action->getRetryDelay(3))->toBe(40); // Third attempt
});

it('uses default values when no attribute', function () {
    $action = new \SysMatter\WorkflowOrchestrator\Tests\Fixtures\Actions\SimpleAction();

    expect($action->getMaxRetries())->toBe(3);
    expect($action->getRetryDelay(1))->toBe(5);
});

it('respects retryable exceptions', function () {
    $action = new RetryableAction();

    $connectionException = new \Illuminate\Http\Client\ConnectionException('Network error');
    $authException = new \Exception('Invalid API key');

    expect($action->shouldRetry($connectionException))->toBeTrue();
    expect($action->shouldRetry($authException))->toBeFalse();
});
