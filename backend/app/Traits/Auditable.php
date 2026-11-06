<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the auditable trait for a model.
     */
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            $model->auditCreate();
        });

        static::updated(function (Model $model) {
            $model->auditUpdate();
        });

        static::deleted(function (Model $model) {
            $model->auditDelete();
        });
    }

    /**
     * Log model creation.
     */
    protected function auditCreate()
    {
        $this->createAuditLog('created', null, $this->getAuditableAttributes());
    }

    /**
     * Log model update.
     */
    protected function auditUpdate()
    {
        $changes = $this->getChanges();

        if (empty($changes)) {
            return; // No actual changes
        }

        $original = array_intersect_key($this->getOriginal(), $changes);

        $this->createAuditLog('updated', $original, $changes);
    }

    /**
     * Log model deletion.
     */
    protected function auditDelete()
    {
        $this->createAuditLog('deleted', $this->getAuditableAttributes(), null);
    }

    /**
     * Create an audit log entry.
     */
    protected function createAuditLog(string $action, ?array $beforeState, ?array $afterState): void
    {
        // Skip if in console (seeding, migrations) but not during unit tests
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return;
        }

        // Get current user if authenticated
        $userId = auth()->id();

        // Get request data safely (skip in console/test contexts)
        $ipAddress = app()->runningInConsole() ? null : request()->ip();
        $userAgent = app()->runningInConsole() ? null : request()->userAgent();

        AuditLog::create([
            'user_id'      => $userId,
            'entity_type'  => get_class($this),
            'entity_id'    => $this->getKey(),
            'action'       => $action,
            'before_state' => $beforeState,
            'after_state'  => $afterState,
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
        ]);
    }

    /**
     * Get auditable attributes (exclude sensitive data).
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // Remove sensitive fields
        $excluded = $this->auditExclude ?? ['password', 'remember_token'];

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * Get audit logs for this model.
     */
    public function auditLogs()
    {
        return AuditLog::where('entity_type', get_class($this))
            ->where('entity_id', $this->getKey())
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
