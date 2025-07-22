# WorkflowOrchestrator

A powerful Laravel package for orchestrating complex workflows with support for sequential and concurrent actions, queue
integration, retry logic, and comprehensive monitoring.

## Features

- **Sequential & Concurrent Actions** - Execute actions in sequence, parallel, or mixed modes
- **Queue Integration** - Works with any Laravel queue driver (Database, Redis, SQS, Pub/Sub, etc.)
- **Smart Retry Logic** - Configurable retries with exponential backoff
- **State Management** - Powered
  by [sysmatter/laravel-status-machina](https://github.com/sysmatter/laravel-status-machina)
- **Activity Logging** - Optional audit trail with Spatie Activity Log
- **Filament Admin UI** - Optional monitoring and management interface
- **High Performance** - Concurrent execution with spatie/fork
- **Developer Friendly** - Clean API with facades and artisan commands

## Requirements

- PHP 8.4+
- Laravel 12.x
- [sysmatter/laravel-status-machina](https://github.com/sysmatter/laravel-status-machina)
- spatie/laravel-activitylog
- spatie/fork

## Installation

```bash
composer require sysmatter/workflow-orchestrator
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --provider="SysMatter\WorkflowOrchestrator\WorkflowOrchestratorServiceProvider"
php artisan migrate
```

## Quick Start

### 1. Configure Your Queue Driver

The package works with any Laravel queue driver. For example, to use Google Pub/Sub:

```bash
composer require sysmatter/laravel-google-pubsub
```

Then configure your `.env`:

```env
QUEUE_CONNECTION=pubsub
# Or use any other queue driver: database, redis, sqs, etc.
```

### 2. Define a Workflow Configuration

```php
<?php

namespace App\Workflows;

use SysMatter\WorkflowOrchestrator\WorkflowConfig;
use App\Actions\Order\ValidateInventory;
use App\Actions\Order\ChargePayment;
use App\Actions\Order\CreateShipment;
use App\Actions\Order\UpdateInventory;
use App\Actions\Order\SendOrderConfirmation;
use App\Actions\Order\NotifyWarehouse;
use App\Actions\Order\UpdateCRM;
use App\Actions\Order\SendAnalytics;

class OrderFulfillmentWorkflow extends WorkflowConfig
{
    public function name(): string
    {
        return 'Order Fulfillment';
    }
    
    public function actions(): array
    {
        return $this->both([
            ValidateInventory::class,     // Sequential - Check stock
            ChargePayment::class,         // Sequential - Process payment
            CreateShipment::class,        // Sequential - Create shipping label
            [                            // Concurrent group
                UpdateInventory::class,
                SendOrderConfirmation::class,
                NotifyWarehouse::class,
                UpdateCRM::class,
            ],
            SendAnalytics::class,         // Sequential - Track metrics
        ]);
    }
}
```

### 3. Create Actions

Synchronous action:

```php
<?php

namespace App\Actions\Order;

use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Attributes\Retry;
use SysMatter\WorkflowOrchestrator\Models\Workflow;

#[Retry(maxAttempts: 3, delay: 5, backoff: true)]
class ValidateInventory extends AbstractAction
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        $order = Order::find($context['order_id']);
        
        foreach ($order->items as $item) {
            if (!$this->checkStock($item)) {
                throw new InsufficientStockException($item);
            }
        }
        
        return ['inventory_validated' => true];
    }
}
```

Queued action:

```php
<?php

namespace App\Actions\Order;

use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderConfirmation extends AbstractAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        $order = Order::find($context['order_id']);
        
        Mail::to($order->customer->email)
            ->send(new OrderConfirmationMail($order));
        
        $workflow->updateContext([
            'confirmation_sent_at' => now(),
        ]);
        
        return ['email_sent' => true];
    }
    
    public function onQueue(): string
    {
        return 'emails';
    }
}
```

### 4. Register & Start Workflows

Register in `config/workflow-orchestrator.php`:

```php
'workflows' => [
    'order_fulfillment' => \App\Workflows\OrderFulfillmentWorkflow::class,
],
```

Start a workflow:

```php
use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;

$workflow = WorkflowMachine::startWorkflow('order_fulfillment', [
    'order_id' => $order->id,
    'payment_method_id' => $paymentMethod->id,
    'shipping_address' => $order->shipping_address,
], [
    'triggered_by' => 'user:' . $order->customer_id,
    'trigger_type' => 'checkout',
]);

// Check status
$status = WorkflowMachine::for($workflow->workflow_id)->getStatus();
```

### 5. Run Queue Workers

Start your queue workers to process async actions:

```bash
php artisan queue:work
```

## Action Types

### Sequential Actions

Actions that run one after another:

```php
public function actions(): array
{
    return $this->sequential([
        ValidateInput::class,
        ProcessData::class,
        SaveResults::class,
    ]);
}
```

### Concurrent Actions

Actions that run in parallel using spatie/fork:

```php
public function actions(): array
{
    return $this->concurrent([
        SendEmailNotification::class,
        SendSlackNotification::class,
        SendSmsNotification::class,
    ]);
}
```

### Mixed Sequential & Concurrent

```php
public function actions(): array
{
    return $this->both([
        PrepareData::class,          // Sequential
        [                           // Concurrent group
            ProcessImageA::class,
            ProcessImageB::class,
            ProcessImageC::class,
        ],
        MergeResults::class,        // Sequential
    ]);
}
```

### Queued Actions

Any action implementing Laravel's `ShouldQueue` interface will be processed asynchronously:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateReport extends AbstractAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function __construct()
    {
        // Specify custom queue if needed
        $this->queue = 'reports';
    }
    
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        // This runs in your queue worker
        $report = $this->generateExpensiveReport($context);
        
        return ['report_url' => $report->url];
    }
}
```

**Note**: When using sync queue driver, queued actions execute immediately. For true async behavior, use a different
queue driver (database, redis, etc.).

### Actions That Wait for External Responses

```php
use SysMatter\WorkflowOrchestrator\Actions\Contracts\WaitsForResponse;

class CallWebhook extends AbstractAction implements WaitsForResponse
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        // Send webhook
        Http::post($context['webhook_url'], [
            'workflow_id' => $workflow->workflow_id,
            'callback_url' => route('workflows.callback', $workflow->workflow_id),
        ]);
        
        return ['webhook_sent' => true];
    }
    
    public function getWaitDuration(): int
    {
        return 300; // Wait up to 5 minutes
    }
    
    public function getTimeoutAction(): ?string
    {
        return HandleWebhookTimeout::class;
    }
}
```

### Publishing Messages from Actions

Actions can still publish messages to external services. This is especially useful when using Pub/Sub:

```php
use SysMatter\WorkflowOrchestrator\Actions\AbstractAction;
use SysMatter\WorkflowOrchestrator\Actions\Contracts\WaitsForResponse;
use SysMatter\GooglePubSub\Facades\PubSub;

class ProcessWithExternalService extends AbstractAction implements WaitsForResponse
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        // Publish message to external service (e.g., Go microservice)
        if (class_exists(\SysMatter\GooglePubSub\Facades\PubSub::class)) {
            PubSub::publish('external.service.process', [
                'workflow_id' => $workflow->workflow_id,
                'action_id' => $workflow->getCurrentAction()->action_id,
                'data' => $context['processing_data'],
            ], [
                'source' => 'workflow-orchestrator',
                'priority' => 'high',
            ]);
        } else {
            // Fallback to HTTP webhook or other method
            Http::post('https://external-service.com/process', [
                'workflow_id' => $workflow->workflow_id,
                'data' => $context['processing_data'],
            ]);
        }
        
        return ['request_sent' => true];
    }
    
    public function getWaitDuration(): int
    {
        return 600; // Wait up to 10 minutes
    }
}
```

## Retry Configuration

Configure retries using attributes:

```php
#[Retry(maxAttempts: 5, delay: 10, backoff: true)]
class FlakeyApiCall extends AbstractAction
{
    // Retry only specific exceptions
    protected array $retryableExceptions = [
        \Illuminate\Http\Client\ConnectionException::class,
        \App\Exceptions\RateLimitException::class,
    ];
    
    // Never retry these exceptions
    protected array $nonRetryableExceptions = [
        \App\Exceptions\InvalidApiKeyException::class,
    ];
}
```

**Important**: The retry count only increments if the exception is retryable. Non-retryable exceptions will immediately
pause the workflow without incrementing the retry count.

## Queue Integration

The package seamlessly integrates with Laravel's queue system:

### Using Different Queue Connections

```php
class ProcessVideo extends AbstractAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        // Process video...
    }
    
    // Use a specific connection
    public function onConnection(): string
    {
        return 'sqs'; // or 'redis', 'database', 'pubsub', etc.
    }
    
    // Use a specific queue
    public function onQueue(): string
    {
        return 'video-processing';
    }
}
```

### Queue Configuration

Configure default queues for workflow actions in your `.env`:

```env
# For database queue
QUEUE_CONNECTION=database

# For Redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# For Google Pub/Sub
QUEUE_CONNECTION=pubsub
GOOGLE_CLOUD_PROJECT_ID=your-project

# For Amazon SQS
QUEUE_CONNECTION=sqs
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/your-account
```

## External Service Integration

When external services need to notify the workflow of completion:

### Option 1: HTTP Callback

Create a callback endpoint:

```php
Route::post('/workflows/{workflowId}/callback', function ($workflowId, Request $request) {
    $workflow = Workflow::where('workflow_id', $workflowId)->firstOrFail();
    $action = $workflow->getCurrentAction();
    
    // Update action with result
    $action->update([
        'completed_at' => now(),
        'result' => $request->all(),
    ]);
    
    // Resume workflow
    WorkflowMachine::for($workflow)->resume();
    
    return response()->json(['success' => true]);
});
```

### Option 2: Message Queue Response

If using Pub/Sub, external services can publish completion messages:

```json
{
  "workflow_id": "550e8400-e29b-41d4-a716-446655440000",
  "action_id": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
  "result": {
    "status": "success",
    "processed_records": 1000
  }
}
```

Then create a listener:

```php
use SysMatter\GooglePubSub\Facades\PubSub;

class WorkflowResponseListener
{
    public function handle()
    {
        $subscriber = PubSub::subscribe('workflow-responses', 'workflow.responses');
        
        $subscriber->handler(function ($data, $message) {
            $workflow = Workflow::where('workflow_id', $data['workflow_id'])->first();
            if ($workflow) {
                $action = $workflow->actions()
                    ->where('action_id', $data['action_id'])
                    ->first();
                    
                if ($action) {
                    $action->update([
                        'completed_at' => now(),
                        'result' => $data['result'],
                    ]);
                    
                    WorkflowMachine::for($workflow)->resume();
                }
            }
            
            $message->ack();
        });
        
        $subscriber->listen();
    }
}
```

## Workflow States

Workflows use a state machine with the following states and transitions:

### States

- `created` - Initial state when workflow is created
- `processing` - Actively executing actions
- `waiting` - Waiting for queued action or external response
- `paused` - Paused due to failure (can be resumed)
- `completed` - Successfully finished all actions
- `failed` - Failed and transitioned to failed state
- `cancelled` - Manually cancelled

### Transitions

- `start` - From `created` to `processing`
- `wait` - From `processing` to `waiting`
- `resume` - From `waiting` or `paused` to `processing`
- `pause` - From `processing` or `waiting` to `paused`
- `complete` - From `processing` to `completed`
- `fail` - From `processing` or `waiting` to `failed`
- `retry` - From `failed` to `processing`
- `cancel` - From various states to `cancelled`

## Artisan Commands

```bash
# Check workflow status
php artisan workflow:status {workflow_id}

# Retry a failed workflow
php artisan workflow:retry {workflow_id}

# Retry from specific action
php artisan workflow:retry {workflow_id} --from-action=3

# Cancel a running workflow
php artisan workflow:cancel {workflow_id}
```

## Filament Admin UI

If you're using Filament, the package includes a comprehensive admin interface:

1. View all workflows with filtering and sorting
2. Monitor workflow progress and action statuses
3. Resume paused workflows
4. Cancel running workflows
5. View detailed context and error information

The Filament integration can be disabled in the config if not needed.

## Advanced Usage

### Custom Workflow Context

Access and modify workflow context throughout execution:

```php
class MyAction extends AbstractAction
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        // Read from context
        $userId = $context['user_id'];
        
        // Update context for subsequent actions
        $workflow->updateContext([
            'processed_at' => now(),
            'processed_by' => $userId,
        ]);
        
        return ['success' => true];
    }
}
```

### Workflow Triggers

Track who and how workflows are triggered:

```php
WorkflowMachine::startWorkflow('process_order', [
    'order_id' => $order->id,
], [
    'triggered_by' => 'system',              // or 'user:123', 'api:stripe'
    'trigger_type' => 'scheduled',           // or 'manual', 'webhook', 'event'
]);
```

### Dynamic Workflow Behavior

Modify workflow behavior based on context:

```php
class ConditionalAction extends AbstractAction
{
    public function execute(Workflow $workflow, array $context = []): mixed
    {
        if ($context['total'] > 1000) {
            // For high-value orders, add fraud check
            $workflow->updateContext([
                'requires_fraud_check' => true,
            ]);
        }
        
        return ['evaluated' => true];
    }
}
```

## Events

The package dispatches events for all state changes:

- `WorkflowStarted`
- `WorkflowCompleted`
- `WorkflowFailed`
- `ActionStarted`
- `ActionCompleted`
- `ActionFailed`

Listen to these events for custom integrations:

```php
use SysMatter\WorkflowOrchestrator\Events\WorkflowCompleted;

class NotifyOnWorkflowComplete
{
    public function handle(WorkflowCompleted $event)
    {
        $workflow = $event->workflow;
        // Send notifications, update analytics, etc.
    }
}
```

## Testing

```php
use SysMatter\WorkflowOrchestrator\Facades\WorkflowMachine;
use Illuminate\Support\Facades\Queue;

public function test_order_fulfillment_workflow()
{
    Queue::fake();
    
    // Start workflow
    $workflow = WorkflowMachine::startWorkflow('order_fulfillment', [
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
    ]);
    
    // Assert workflow created
    $this->assertDatabaseHas('workflows', [
        'workflow_id' => $workflow->workflow_id,
        'type' => 'order_fulfillment',
        'status' => 'processing',
    ]);
    
    // Assert jobs were dispatched
    Queue::assertPushed(WorkflowActionJob::class, function ($job) use ($workflow) {
        return $job->workflowId === $workflow->id;
    });
}
```

### Testing Tips

1. **Queue Testing**: When testing queued actions, use a non-sync queue driver with `Queue::fake()`:
   ```php
   config(['queue.default' => 'database']);
   Queue::fake();
   ```

2. **Activity Logging**: Activity logging is typically disabled in tests for performance. Enable it for specific tests:
   ```php
   config(['activitylog.enabled' => true]);
   config(['workflow-orchestrator.activity_log.enabled' => true]);
   ```

3. **State Transitions**: Use the state machine methods to check states:
   ```php
   expect($workflow->stateIs('completed'))->toBeTrue();
   expect($workflow->canTransitionTo('cancelled'))->toBeFalse();
   ```

## Configuration

```php
return [
    // Register workflow configurations
    'workflows' => [
        'order_fulfillment' => \App\Workflows\OrderFulfillmentWorkflow::class,
        'user_onboarding' => \App\Workflows\UserOnboardingWorkflow::class,
        'subscription_renewal' => \App\Workflows\SubscriptionRenewalWorkflow::class,
    ],
    
    // Default retry settings
    'retry' => [
        'max_attempts' => 3,
        'delay' => 5,
        'backoff' => true,
    ],
    
    // Activity logging
    'activity_log' => [
        'enabled' => true, // Enable workflow-specific activity logging
        'log_name' => 'workflow', // Log name for Spatie Activity Log
    ],
    
    // Filament admin UI
    'filament' => [
        'enabled' => true,
        'register_resource' => true,
    ],
];
```

### Activity Logging

Activity logging requires both the global Spatie activity log to be enabled AND the workflow-specific setting:

```php
// Both must be true for workflow activities to be logged
config('activitylog.enabled') // Global Spatie setting
config('workflow-orchestrator.activity_log.enabled') // Package-specific setting
```
