<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use SysMatter\WorkflowOrchestrator\Database\Factories\WorkflowActionFactory;

/**
* @property int $id
* @property int $workflow_id
* @property string $action_id
* @property string $class
* @property int $index
* @property int $group_index
* @property bool $is_concurrent
* @property array|null $context
* @property array|null $result
* @property array|null $exception
* @property int $retry_count
* @property int $max_retries
* @property Carbon|null $started_at
* @property Carbon|null $completed_at
* @property Carbon|null $failed_at
* @property Carbon $created_at
* @property Carbon $updated_at
*/
class WorkflowAction extends Model
{
    /** @use HasFactory<WorkflowActionFactory> */
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'workflow_id',
        'action_id',
        'class',
        'index',
        'group_index',
        'is_concurrent',
        'context',
        'started_at',
        'completed_at',
        'failed_at',
        'retry_count',
        'max_retries',
        'result',
        'exception',
    ];

    protected $casts = [
        'context' => 'array',
        'result' => 'array',
        'exception' => 'array',
        'is_concurrent' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'index' => 'integer',
        'group_index' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function isFailed(): bool
    {
        return $this->failed_at !== null && $this->retry_count >= $this->max_retries;
    }

    public function canRetry(): bool
    {
        return $this->failed_at !== null && $this->retry_count < $this->max_retries;
    }

    protected static function newFactory(): WorkflowActionFactory
    {
        return WorkflowActionFactory::new();
    }
}
