<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records authentication events (login, logout, failed login) to the
 * audit_logs table. This is intentionally a fail-safe listener — any
 * persistence error is logged as a warning so it cannot break the
 * authentication flow itself.
 */
class LogAuthEvent
{
    /**
     * Handle a successful login event.
     */
    public function handleLogin(Login $event): void
    {
        try {
            $user = $event->user;

            if (! $user) {
                return;
            }

            AuditLog::create([
                'user_id'      => $user->getKey(),
                'entity_type'  => 'App\\Models\\User',
                'entity_id'    => $user->getKey(),
                'action'       => 'login',
                'before_state' => null,
                'after_state'  => null,
                'ip_address'   => $this->resolveIp(),
                'user_agent'   => $this->resolveUserAgent(),
            ]);
        } catch (Throwable $e) {
            Log::warning('LogAuthEvent: failed to record login audit entry', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a logout event.
     */
    public function handleLogout(Logout $event): void
    {
        try {
            $user   = $event->user;
            $userId = $user?->getKey();

            AuditLog::create([
                'user_id'      => $userId,
                'entity_type'  => 'App\\Models\\User',
                'entity_id'    => $userId,
                'action'       => 'logout',
                'before_state' => null,
                'after_state'  => null,
                'ip_address'   => $this->resolveIp(),
                'user_agent'   => $this->resolveUserAgent(),
            ]);
        } catch (Throwable $e) {
            Log::warning('LogAuthEvent: failed to record logout audit entry', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a failed login attempt. user_id is null because we don't know
     * (and don't want to expose) which account was targeted; we keep only
     * the supplied identifier (never the password).
     */
    public function handleFailed(Failed $event): void
    {
        try {
            $credentials = is_array($event->credentials) ? $event->credentials : [];
            $username    = $credentials['username']
                ?? $credentials['email']
                ?? '<unknown>';

            AuditLog::create([
                'user_id'      => null,
                'entity_type'  => 'App\\Models\\User',
                'entity_id'    => null,
                'action'       => 'login_failed',
                'before_state' => ['username' => $username],
                'after_state'  => null,
                'ip_address'   => $this->resolveIp(),
                'user_agent'   => $this->resolveUserAgent(),
            ]);
        } catch (Throwable $e) {
            Log::warning('LogAuthEvent: failed to record failed-login audit entry', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class   => 'handleLogin',
            Logout::class  => 'handleLogout',
            Failed::class  => 'handleFailed',
        ];
    }

    /**
     * Safely resolve the request IP without throwing when no request is bound.
     */
    private function resolveIp(): ?string
    {
        try {
            return request()->ip();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Safely resolve and truncate the user agent to the column width.
     */
    private function resolveUserAgent(): ?string
    {
        try {
            $ua = request()->userAgent();
        } catch (Throwable) {
            return null;
        }

        if ($ua === null) {
            return null;
        }

        return substr($ua, 0, 500);
    }
}
