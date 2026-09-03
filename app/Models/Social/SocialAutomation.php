<?php

namespace App\Models\Social;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A recurring campaign ("every day at 8pm, post the daily quiz").
 *
 * `is_active` is the emergency stop: the runner checks it immediately before
 * every execution, so switching it off in the UI halts the automation even if a
 * run is already scheduled.
 */
class SocialAutomation extends Model {

    protected $table = 'social_automations';

    protected $fillable = [
        'name', 'description', 'source_type', 'source_config', 'frequency',
        'days_of_week', 'run_time', 'interval_minutes', 'timezone', 'platforms',
        'social_template_id', 'social_hashtag_group_id', 'social_campaign_id',
        'auto_publish', 'is_active', 'last_run_at', 'next_run_at', 'run_count',
        'last_error', 'created_by',
    ];

    protected $casts = [
        'source_config' => 'array',
        'days_of_week'  => 'array',
        'platforms'     => 'array',
        'auto_publish'  => 'boolean',
        'is_active'     => 'boolean',
        'last_run_at'   => 'datetime',
        'next_run_at'   => 'datetime',
    ];

    const SOURCE_TYPES = [
        'daily_quiz'      => 'Daily Quiz',
        'random_quiz'     => 'Random Quiz',
        'current_affairs' => 'Current Affairs',
        'new_blog'        => 'Latest Blog / Page',
        'random_question' => 'Random Question',
        'custom'          => 'Custom (template only)',
    ];

    const FREQUENCIES = [
        'daily'    => 'Every day',
        'weekly'   => 'Selected days each week',
        'monthly'  => 'Monthly',
        'interval' => 'Every N minutes',
    ];

    public function template() {
        return $this->belongsTo(SocialTemplate::class, 'social_template_id');
    }

    public function hashtagGroup() {
        return $this->belongsTo(SocialHashtagGroup::class, 'social_hashtag_group_id');
    }

    public function campaign() {
        return $this->belongsTo(SocialCampaign::class, 'social_campaign_id');
    }

    public function scopeDue($query) {
        return $query->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    /**
     * Next fire time after $from, in the automation's own timezone.
     *
     * Returns null for a weekly rule with no days selected - that is a
     * misconfiguration, and silently defaulting to "every day" would post
     * content the admin never asked for.
     */
    public function calculateNextRun(?Carbon $from = null): ?Carbon {
        $tz   = $this->timezone ?: config('app.timezone', 'UTC');
        $from = ($from ?: now())->copy()->setTimezone($tz);

        if ($this->frequency === 'interval') {
            $minutes = max(5, (int) $this->interval_minutes);
            return $from->copy()->addMinutes($minutes)->setTimezone(config('app.timezone'));
        }

        $time    = $this->run_time ? Carbon::parse($this->run_time) : Carbon::parse('09:00');
        $next    = $from->copy()->setTime((int) $time->hour, (int) $time->minute, 0);
        if ($next->lte($from)) {
            $next->addDay();
        }

        if ($this->frequency === 'weekly') {
            $days = array_map('intval', $this->days_of_week ?? []);
            if (!$days) {
                return null;
            }
            // Walk forward at most a week to land on the next selected weekday.
            for ($i = 0; $i < 8; $i++) {
                if (in_array($next->dayOfWeek, $days, true)) {
                    break;
                }
                $next->addDay();
            }
        }

        if ($this->frequency === 'monthly') {
            $day = (int) ($this->source_config['day_of_month'] ?? 1);
            $next = $from->copy()->setTime((int) $time->hour, (int) $time->minute, 0)
                ->day(min($day, $from->daysInMonth));
            if ($next->lte($from)) {
                $next = $next->addMonthNoOverflow()->day(min($day, $next->copy()->addMonthNoOverflow()->daysInMonth));
            }
        }

        return $next->setTimezone(config('app.timezone'));
    }

    public function getSourceNameAttribute(): string {
        return self::SOURCE_TYPES[$this->source_type] ?? ucfirst((string) $this->source_type);
    }

    public function getFrequencyNameAttribute(): string {
        return self::FREQUENCIES[$this->frequency] ?? ucfirst((string) $this->frequency);
    }
}
