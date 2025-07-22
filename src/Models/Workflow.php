<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use SysMatter\StatusMachina\Traits\HasStateMachine;
use SysMatter\WorkflowOrchestrator\Database\Factories\WorkflowFactory;
use SysMatter\WorkflowOrchestrator\States\WorkflowState;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * @property string $workflow_id
 * @property string $correlation_id
 * @property string $type
 * @property array $context
 * @property string|null $triggered_by
 * @property string|null $trigger_type
 * @property int $current_action_index
 * @property string $status
 * @property Carbon|null $paused_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Workflow extends Model
{
    use HasStateMachine;
    use LogsActivity;
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'correlation_id',
        'type',
        'context',
        'triggered_by',
        'trigger_type',
        'status',
        'current_action_index',
        'paused_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'current_action_index' => 'integer',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Configure status machina
    protected array $stateMachines = [
        'status' => WorkflowState::class,
    ];

    protected static function newFactory()
    {
        return WorkflowFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'current_action_index', 'context'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('workflow');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('index');
    }

    public function getCurrentAction(): ?WorkflowAction
    {
        /** @var WorkflowAction|null */
        return $this->actions()->where('index', $this->current_action_index)->first();
    }

    public function getNextAction(): ?WorkflowAction
    {
        /** @var WorkflowAction|null */
        return $this->actions()->where('index', '>', $this->current_action_index)
            ->orderBy('index')
            ->first();
    }

    public function updateContext(array $data): void
    {
        $this->update([
            'context' => array_merge($this->context ?? [], $data)
        ]);
    }
}
