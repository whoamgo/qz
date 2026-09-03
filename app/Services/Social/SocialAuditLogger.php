<?php

namespace App\Services\Social;

use App\Models\Social\SocialAuditLog;
use App\Services\Social\Support\SocialHttpClient;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the activity log.
 *
 * Context is redacted through SocialHttpClient::redact() on the way in, so a
 * caller that carelessly passes an access token still cannot write one to the
 * database. Logging must never break the action being logged, so failures here
 * are swallowed.
 */
class SocialAuditLogger {

    public static function record(string $action, array $options = []): void {
        try {
            $admin = Auth::guard('admin')->user();

            SocialAuditLog::create([
                'admin_id'     => $admin?->id,
                'admin_name'   => $admin?->name ?? ($options['admin_name'] ?? 'System'),
                'action'       => $action,
                'platform'     => $options['platform'] ?? null,
                'subject_type' => $options['subject_type'] ?? null,
                'subject_id'   => $options['subject_id'] ?? null,
                'result'       => $options['result'] ?? 'success',
                'description'  => isset($options['description'])
                    ? SocialHttpClient::redactString((string) $options['description'])
                    : null,
                'context'      => isset($options['context'])
                    ? SocialHttpClient::redact($options['context'])
                    : null,
                'ip'           => request()?->ip(),
                'user_agent'   => mb_substr((string) request()?->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // An audit write must never take down the operation it describes.
        }
    }

    /** Convenience for logging against a model. */
    public static function forModel(string $action, $model, array $options = []): void {
        self::record($action, array_merge($options, [
            'subject_type' => class_basename($model),
            'subject_id'   => $model->id ?? null,
        ]));
    }

    public static function failure(string $action, string $description, array $options = []): void {
        self::record($action, array_merge($options, [
            'result'      => 'failed',
            'description' => $description,
        ]));
    }
}
