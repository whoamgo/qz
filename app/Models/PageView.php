<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Read-model over analytics_events scoped to page views. Lets the admin panel
 * query PageView::valid()->... naturally while everything lives in one table.
 */
class PageView extends AnalyticsEvent {
    protected static function booted(): void {
        static::addGlobalScope('page_view', function (Builder $q) {
            $q->where('event_type', self::TYPE_PAGE_VIEW);
        });
    }
}
