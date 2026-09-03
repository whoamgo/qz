<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Keep the raw analytics table bounded (analytics.retention_days).
Schedule::command('analytics:prune')->daily();

/*
| Social Media Center.
|
| These run only where Laravel's scheduler itself is wired up (a single
| `schedule:run` cron entry). Installs without it use the panel's own cron
| system instead - the same work is registered there as the "Social Media
| Publisher" and "Social Media Sync" jobs, so publishing works either way and
| never depends on a browser being open.
|
| withoutOverlapping matters for the publisher: a slow upload can outlast the
| minute, and two concurrent runs would fight over the same jobs. (They would
| not double-publish - jobs are leased - but the lock avoids the churn.)
*/
Schedule::command('social:work --seconds=50 --jobs=20')->everyMinute()->withoutOverlapping();
Schedule::command('social:automations')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('social:sync')->hourly()->withoutOverlapping();
