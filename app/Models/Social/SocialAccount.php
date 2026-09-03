<?php

namespace App\Models\Social;

use App\Constants\SocialStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * A connected platform account.
 *
 * Security note: `credentials` holds the access token, refresh token and any
 * page/business identifiers as one encrypted JSON blob. It is in `$hidden`, so
 * `toArray()`/`toJson()` - and therefore every JSON response and every Blade
 * `@json()` - can never leak it. Read it only through credential() / setCredentials().
 */
class SocialAccount extends Model {

    protected $table = 'social_accounts';

    protected $fillable = [
        'platform', 'name', 'username', 'external_id', 'avatar_url', 'profile_url',
        'status', 'is_default', 'scopes', 'token_expires_at', 'token_refreshed_at',
        'last_sync_at', 'last_checked_at', 'last_error',
        'followers', 'following', 'media_count', 'meta', 'connected_by',
    ];

    protected $casts = [
        'credentials'        => 'encrypted:array',
        'meta'               => 'array',
        'is_default'         => 'boolean',
        'status'             => 'integer',
        'token_expires_at'   => 'datetime',
        'token_refreshed_at' => 'datetime',
        'last_sync_at'       => 'datetime',
        'last_checked_at'    => 'datetime',
    ];

    /** Never serialise credentials, whatever the caller does. */
    protected $hidden = ['credentials'];

    /* ------------------------------------------------------------- Relations */

    public function posts() {
        return $this->hasMany(SocialPostPlatform::class, 'social_account_id');
    }

    public function analytics() {
        return $this->hasMany(SocialAnalytic::class, 'social_account_id');
    }

    /* ---------------------------------------------------------------- Scopes */

    public function scopeConnected($query) {
        return $query->where('status', SocialStatus::ACCOUNT_CONNECTED);
    }

    public function scopePlatform($query, string $platform) {
        return $query->where('platform', $platform);
    }

    /* ------------------------------------------------------------ Credentials */

    /** Single credential value, e.g. access_token, refresh_token, page_id. */
    public function credential(string $key, $default = null) {
        $creds = $this->credentials ?? [];
        return $creds[$key] ?? $default;
    }

    /** Merges values into the encrypted blob without clobbering the rest. */
    public function setCredentials(array $values): void {
        $this->credentials = array_merge($this->credentials ?? [], $values);
    }

    public function forgetCredentials(): void {
        $this->credentials = null;
    }

    public function accessToken(): ?string {
        return $this->credential('access_token');
    }

    /**
     * True when the stored token is expired or close enough that a publish
     * started now would probably outlive it.
     */
    public function tokenExpired(int $skewSeconds = 120): bool {
        if (!$this->token_expires_at) {
            return false; // Long-lived / non-expiring token.
        }
        return $this->token_expires_at->lte(now()->addSeconds($skewSeconds));
    }

    public function isConnected(): bool {
        return $this->status === SocialStatus::ACCOUNT_CONNECTED && (bool) $this->accessToken();
    }

    /** Usable right now for publishing: connected and not expired. */
    public function isPublishable(): bool {
        return $this->isConnected() && !$this->tokenExpired();
    }

    /* ---------------------------------------------------------------- Display */

    public function getPlatformNameAttribute(): string {
        return SocialStatus::platformName($this->platform);
    }

    public function getStatusNameAttribute(): string {
        return SocialStatus::ACCOUNT_STATUSES[$this->status] ?? 'Unknown';
    }

    public function getStatusClassAttribute(): string {
        return match ($this->status) {
            SocialStatus::ACCOUNT_CONNECTED        => 'success',
            SocialStatus::ACCOUNT_TOKEN_EXPIRED    => 'warning',
            SocialStatus::ACCOUNT_PERMISSION_ISSUE => 'warning',
            SocialStatus::ACCOUNT_ERROR            => 'danger',
            default                                => 'secondary',
        };
    }

    public function getDisplayNameAttribute(): string {
        return $this->name ?: ($this->username ?: $this->platform_name);
    }

    /** Marks the account unusable and records why, without storing secrets. */
    public function markProblem(int $status, ?string $message = null): void {
        $this->status          = $status;
        $this->last_error      = $message ? mb_substr($message, 0, 500) : null;
        $this->last_checked_at = now();
        $this->save();
    }
}
