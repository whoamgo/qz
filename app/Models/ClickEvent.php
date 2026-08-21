<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Read-model over analytics_events scoped to click events. Business events
 * (quiz_started, room_created, ...) stay on AnalyticsEvent with their own type.
 */
class ClickEvent extends AnalyticsEvent {
    protected static function booted(): void {
        static::addGlobalScope('click', function (Builder $q) {
            $q->where('event_type', self::TYPE_CLICK);
        });
    }
}
