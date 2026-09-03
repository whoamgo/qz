<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * One recorded call to a platform API.
 *
 * Kept separately from the job so the UI can show the full history ("attempt 2
 * of 3 failed with a 429") rather than just the latest error. Request and
 * response bodies are stored as redacted summaries - see SocialHttpClient.
 */
class SocialPublishAttempt extends Model {

    protected $table = 'social_publish_attempts';

    protected $fillable = [
        'social_publish_job_id', 'social_post_platform_id', 'platform', 'attempt_no',
        'status', 'endpoint', 'response_code', 'request_summary', 'response_summary',
        'error_code', 'error_message', 'retryable', 'started_at', 'finished_at', 'duration_ms',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'retryable'       => 'boolean',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
    ];

    public function job() {
        return $this->belongsTo(SocialPublishJob::class, 'social_publish_job_id');
    }

    public function target() {
        return $this->belongsTo(SocialPostPlatform::class, 'social_post_platform_id');
    }

    public function getStatusClassAttribute(): string {
        return match ($this->status) {
            'success' => 'success',
            'skipped' => 'secondary',
            default   => 'danger',
        };
    }
}
