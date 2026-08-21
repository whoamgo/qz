<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;

/**
 * Deletes analytics events older than analytics.retention_days so the raw event
 * table cannot grow without bound. Run daily via the scheduler (routes/console).
 */
class PruneAnalytics extends Command {
    protected $signature = 'analytics:prune {--days= : Override the configured retention days}';
    protected $description = 'Delete analytics events older than the configured retention window';

    public function handle(): int {
        $days = (int) ($this->option('days') ?: config('analytics.retention_days', 90));

        if ($days <= 0) {
            $this->info('Retention pruning is disabled (retention_days = 0).');
            return self::SUCCESS;
        }

        $cutoff  = now()->subDays($days);
        $deleted = 0;
        // Delete in chunks so a huge backlog never locks the table for long.
        do {
            $count = AnalyticsEvent::where('created_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $count;
        } while ($count > 0);

        $this->info("Pruned {$deleted} analytics event(s) older than {$days} day(s).");
        return self::SUCCESS;
    }
}
