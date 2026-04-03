<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicMapping extends Model
{
    use HasFactory;

    const ACTION_UPDATE_BATCH_STEP    = 'update_batch_step';
    const ACTION_UPDATE_WORK_ORDER_QTY = 'update_work_order_qty';
    const ACTION_CREATE_ISSUE         = 'create_issue';
    const ACTION_UPDATE_LINE_STATUS   = 'update_line_status';
    const ACTION_SET_WORK_ORDER_STATUS = 'set_work_order_status';
    const ACTION_LOG_EVENT            = 'log_event';
    const ACTION_WEBHOOK_FORWARD      = 'webhook_forward';

    const ACTION_LABELS = [
        self::ACTION_UPDATE_BATCH_STEP     => 'Update Batch Step',
        self::ACTION_UPDATE_WORK_ORDER_QTY => 'Update Work Order Qty',
        self::ACTION_CREATE_ISSUE          => 'Create Issue',
        self::ACTION_UPDATE_LINE_STATUS    => 'Update Line Status',
        self::ACTION_SET_WORK_ORDER_STATUS => 'Set Work Order Status',
        self::ACTION_LOG_EVENT             => 'Log Event Only',
        self::ACTION_WEBHOOK_FORWARD       => 'Forward to Webhook',
    ];

    protected $fillable = [
        'machine_topic_id',
        'description',
        'field_path',
        'action_type',
        'action_params',
        'condition_expr',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'action_params' => 'array',
            'is_active'     => 'boolean',
            'priority'      => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(MachineTopic::class, 'machine_topic_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action_type] ?? $this->action_type;
    }
}
