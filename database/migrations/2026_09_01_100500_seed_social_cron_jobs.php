<?php

use App\Constants\Status;
use App\Models\CronJob;
use App\Models\CronSchedule;
use Illuminate\Database\Migrations\Migration;

/**
 * Registers the Social Media Center with the panel's existing cron system.
 *
 * The panel already has a cron runner that an external cron hits; adding the
 * social jobs here means publishing works on shared hosting with no queue
 * worker and no extra setup beyond the cron entry the install already needs.
 *
 * Both jobs are created as *stopped*. Nothing starts publishing on its own
 * because a migration ran - the admin turns them on from Cron Jobs when they
 * are ready.
 */
return new class extends Migration {

    public function up(): void {
        // The publisher wants the shortest interval available; the sync job is
        // hourly work. Fall back to whatever schedules exist on an install that
        // customised them.
        $frequent = CronSchedule::orderBy('interval')->first();
        $hourly   = CronSchedule::where('interval', '>=', 3600)->orderBy('interval')->first() ?: $frequent;

        if (!$frequent) {
            return;
        }

        $jobs = [
            [
                'name'   => 'Social Media Publisher',
                'alias'  => 'social_publish',
                'action' => [\App\Http\Controllers\CronController::class, 'socialPublish'],
                'schedule' => $frequent->id,
            ],
            [
                'name'   => 'Social Media Sync',
                'alias'  => 'social_sync',
                'action' => [\App\Http\Controllers\CronController::class, 'socialSync'],
                'schedule' => $hourly->id,
            ],
        ];

        foreach ($jobs as $job) {
            if (CronJob::where('alias', $job['alias'])->exists()) {
                continue;
            }

            $cron                   = new CronJob();
            $cron->name             = $job['name'];
            $cron->alias            = $job['alias'];
            $cron->action           = $job['action'];
            $cron->is_default       = Status::YES;
            $cron->is_running       = Status::NO;
            $cron->cron_schedule_id = $job['schedule'];
            $cron->next_run         = now();
            $cron->save();
        }
    }

    public function down(): void {
        CronJob::whereIn('alias', ['social_publish', 'social_sync'])->delete();
    }
};
