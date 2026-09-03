<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of who did what.
 *
 * Rows are written by App\Services\Social\SocialAuditLogger, which redacts the
 * context before it reaches this model - nothing token-shaped is ever stored.
 */
class SocialAuditLog extends Model {

    protected $table = 'social_audit_logs';

    protected $fillable = [
        'admin_id', 'admin_name', 'action', 'platform', 'subject_type', 'subject_id',
        'result', 'description', 'context', 'ip', 'user_agent',
    ];

    protected $casts = ['context' => 'array'];

    /** Actions that appear in the activity-log filter. */
    const ACTIONS = [
        'account.connect'     => 'Account connected',
        'account.disconnect'  => 'Account disconnected',
        'account.reconnect'   => 'Account reconnected',
        'account.test'        => 'Connection tested',
        'account.token_refresh' => 'Token refreshed',
        'post.create'         => 'Post created',
        'post.update'         => 'Post updated',
        'post.delete'         => 'Post deleted',
        'post.duplicate'      => 'Post duplicated',
        'post.schedule'       => 'Post scheduled',
        'post.reschedule'     => 'Post rescheduled',
        'post.cancel'         => 'Post cancelled',
        'post.publish'        => 'Post published',
        'post.retry'          => 'Publish retried',
        'post.submit_approval'=> 'Submitted for approval',
        'post.approve'        => 'Post approved',
        'post.reject'         => 'Post rejected',
        'media.upload'        => 'Media uploaded',
        'media.delete'        => 'Media deleted',
        'campaign.save'       => 'Campaign saved',
        'template.save'       => 'Template saved',
        'hashtags.save'       => 'Hashtag group saved',
        'automation.save'     => 'Automation saved',
        'automation.toggle'   => 'Automation toggled',
        'settings.update'     => 'Settings updated',
        'bulk.import'         => 'Bulk import run',
        'ai.generate'         => 'AI content generated',
    ];

    public function admin() {
        return $this->belongsTo(\App\Models\Admin::class, 'admin_id');
    }

    public function scopeAction($query, string $action) {
        return $query->where('action', $action);
    }

    public function getActionNameAttribute(): string {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace(['.', '_'], ' ', (string) $this->action));
    }

    public function getResultClassAttribute(): string {
        return match ($this->result) {
            'success' => 'success',
            'failed'  => 'danger',
            'denied'  => 'warning',
            default   => 'secondary',
        };
    }
}
