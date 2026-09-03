<?php

namespace App\Models\Social;

use App\Constants\SocialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A unit of background work.
 *
 * This install has no queue worker, so the Social Center keeps its own durable
 * job table drained by cron (and by `php artisan social:work`). Claiming uses a
 * lease: a runner stamps `reserved_at` + a random `reserved_token` inside a
 * transaction, then only acts on rows it still owns. A runner that dies mid-job
 * simply lets the lease expire and another run picks the job back up.
 */
class SocialPublishJob extends Model {

    protected $table = 'social_publish_jobs';

    protected $fillable = [
        'type', 'social_post_id', 'social_post_platform_id', 'social_account_id', 'platform',
        'status', 'idempotency_key', 'attempts', 'max_attempts', 'priority',
        'available_at', 'reserved_at', 'reserved_token', 'completed_at', 'payload', 'last_error',
    ];

    protected $casts = [
        'payload'      => 'array',
        'available_at' => 'datetime',
        'reserved_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = ['reserved_token'];

    /* ------------------------------------------------------------- Relations */

    public function post() {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function target() {
        return $this->belongsTo(SocialPostPlatform::class, 'social_post_platform_id');
    }

    public function account() {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function attemptLogs() {
        return $this->hasMany(SocialPublishAttempt::class, 'social_publish_job_id');
    }

    /* ---------------------------------------------------------------- Scopes */

    public function scopePending($query) {
        return $query->where('status', SocialStatus::JOB_QUEUED);
    }

    public function scopeDue($query) {
        return $query->where('status', SocialStatus::JOB_QUEUED)
            ->where(function ($q) {
                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
            });
    }

    /* ------------------------------------------------------------- Claim/lease */

    /**
     * Atomically claims up to $limit due jobs for this runner.
     *
     * Uses SELECT ... FOR UPDATE SKIP LOCKED where the driver supports it so two
     * overlapping cron runs split the work instead of fighting over it.
     *
     * @return \Illuminate\Support\Collection<int,self>
     */
    public static function claim(string $token, int $limit = 5, int $leaseSeconds = 600) {
        $ids = [];

        DB::transaction(function () use (&$ids, $token, $limit, $leaseSeconds) {
            $query = static::query()
                ->where(function ($q) {
                    $q->where('status', SocialStatus::JOB_QUEUED)
                        ->where(function ($q2) {
                            $q2->whereNull('available_at')->orWhere('available_at', '<=', now());
                        });
                })
                // Reclaim jobs whose lease expired - the runner that held them
                // died (worker crash, PHP timeout) without finishing.
                ->orWhere(function ($q) use ($leaseSeconds) {
                    $q->where('status', SocialStatus::JOB_PROCESSING)
                        ->where('reserved_at', '<', now()->subSeconds($leaseSeconds));
                })
                ->orderBy('priority')
                ->orderBy('id')
                ->limit($limit);

            try {
                $ids = $query->lockForUpdate()->pluck('id')->all();
            } catch (\Throwable $e) {
                // SQLite has no row locks; the single-writer model makes this safe.
                $ids = $query->pluck('id')->all();
            }

            if ($ids) {
                static::whereIn('id', $ids)->update([
                    'status'         => SocialStatus::JOB_PROCESSING,
                    'reserved_at'    => now(),
                    'reserved_token' => $token,
                    'updated_at'     => now(),
                ]);
            }
        });

        if (!$ids) {
            return collect();
        }

        // Re-read with the token filter: anything another runner grabbed between
        // the transaction commit and now is excluded.
        return static::whereIn('id', $ids)->where('reserved_token', $token)->get();
    }

    public static function newToken(): string {
        return (string) Str::uuid();
    }

    /** True while this runner still holds the lease. */
    public function stillOwned(string $token): bool {
        return static::where('id', $this->id)->where('reserved_token', $token)->exists();
    }

    public function complete(): void {
        $this->status         = SocialStatus::JOB_COMPLETED;
        $this->completed_at   = now();
        $this->reserved_token = null;
        $this->reserved_at    = null;
        $this->save();
    }

    /** Re-queues the job for a later attempt with backoff already applied. */
    public function release(\DateTimeInterface $availableAt, ?string $error = null): void {
        $this->status         = SocialStatus::JOB_QUEUED;
        $this->available_at   = $availableAt;
        $this->reserved_at    = null;
        $this->reserved_token = null;
        $this->last_error     = $error;
        $this->save();
    }

    public function fail(?string $error = null): void {
        $this->status         = SocialStatus::JOB_FAILED;
        $this->last_error     = $error;
        $this->completed_at   = now();
        $this->reserved_at    = null;
        $this->reserved_token = null;
        $this->save();
    }

    public function getStatusClassAttribute(): string {
        return match ($this->status) {
            SocialStatus::JOB_COMPLETED  => 'success',
            SocialStatus::JOB_PROCESSING => 'primary',
            SocialStatus::JOB_FAILED     => 'danger',
            SocialStatus::JOB_CANCELLED  => 'dark',
            default                      => 'info',
        };
    }
}
