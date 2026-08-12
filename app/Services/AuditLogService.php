<?php

namespace App\Services;

use App\Models\ActivityLog;

class AuditLogService
{
    public static function recordUser(string $action, ?string $description = null, array $context = []): void
    {
        self::record('user', $action, $description, $context);
    }

    public static function recordAdmin(string $action, $subject = null, ?string $description = null, array $context = []): void
    {
        self::record('admin', $action, $description, $context, $subject);
    }

    public static function recordTransaction(string $action, $subject = null, ?string $description = null, array $context = []): void
    {
        self::record('transaction', $action, $description, $context, $subject);
    }

    /**
     * Writes the log row directly (no DB::afterCommit) — every call site in
     * this app already places its AuditLogService call either (a) inside the
     * same DB::transaction() block as the business writes it describes, so a
     * later failure in that block rolls the log back together with them, or
     * (b) after a transaction has already rolled back (e.g. the
     * InsufficientStockException render callback in bootstrap/app.php), so
     * there's nothing left to roll back. afterCommit would add no extra
     * safety here, and it silently never fires inside tests that use
     * RefreshDatabase (the test's own wrapping transaction never truly
     * commits), which made every log assertion fail.
     */
    private static function record(string $type, string $action, ?string $description, array $context, $subject = null): void
    {
        $causer = auth()->user();
        $context = self::sanitize($context);
        $request = request();

        ActivityLog::create([
            'type' => $type,
            'user_id' => $causer?->id,
            'email' => $causer?->email,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'action' => $action,
            'description' => $description,
            'context' => array_merge($context, [
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]),
        ]);
    }

    private static function sanitize(array $context): array
    {
        $sensitive = [
            'password', 'password_confirmation', 'current_password', 'token',
            'snap_token', 'server_key', 'card_number', 'cvv', 'exp_month', 'exp_year',
        ];

        array_walk_recursive($context, function (&$value, $key) use ($sensitive) {
            if (is_string($key) && in_array($key, $sensitive, true)) {
                $value = '***REDACTED***';
            }
        });

        return $context;
    }
}
