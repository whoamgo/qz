<?php

namespace App\Http\Controllers;

use App\Constants\Status;
use App\Lib\CurlRequest;
use App\Models\CronJob;
use App\Models\CronJobLog;
use App\Models\User;
use Carbon\Carbon;

class CronController extends Controller {
    public function cron() {
        $general            = gs();
        $general->last_cron = now();
        $general->save();

        $crons = CronJob::with('schedule');

        if (request()->alias) {
            $crons->where('alias', request()->alias);
        } else {
            $crons->where('next_run', '<', now())->where('is_running', Status::YES);
        }
        $crons = $crons->get();
        foreach ($crons as $cron) {
            $cronLog              = new CronJobLog();
            $cronLog->cron_job_id = $cron->id;
            $cronLog->start_at    = now();
            if ($cron->is_default) {
                $controller = new $cron->action[0];
                try {
                    $method = $cron->action[1];
                    $controller->$method();
                } catch (\Exception $e) {
                    $cronLog->error = $e->getMessage();
                }
            } else {
                try {
                    CurlRequest::curlContent($cron->url);
                } catch (\Exception $e) {
                    $cronLog->error = $e->getMessage();
                }
            }
            $cron->last_run = now();
            $cron->next_run = now()->addSeconds((int) $cron->schedule->interval);
            $cron->save();

            $cronLog->end_at = $cron->last_run;

            $startTime         = Carbon::parse($cronLog->start_at);
            $endTime           = Carbon::parse($cronLog->end_at);
            $diffInSeconds     = $startTime->diffInSeconds($endTime);
            $cronLog->duration = $diffInSeconds;
            $cronLog->save();
        }
        if (request()->target == 'all') {
            $notify[] = ['success', 'Cron executed successfully'];
            return back()->withNotify($notify);
        }
        if (request()->alias) {
            $notify[] = ['success', keyToTitle(request()->alias) . ' executed successfully'];
            return back()->withNotify($notify);
        }
    }

    public function planExpired() {
        $users = User::where('expired_date', '<', now())->limit(50)->get();
        foreach ($users as $user) {
            $user->expired_date = null;
            $user->plan_id      = 0;
            $user->exam_limit   = 0;
            $user->save();
        }
    }

    /**
     * Social Media Center: drains the publishing queue and fires due automations.
     *
     * This is what makes "close the browser and the post still goes out" true on
     * a host with nothing but a plain cron entry. The time budget keeps the run
     * inside PHP's execution limit; anything left over is picked up next time.
     */
    public function socialPublish() {
        $runner = app(\App\Services\Social\SocialQueueRunner::class);
        $runner->run(maxSeconds: 45, maxJobs: 20);

        app(\App\Services\Social\SocialAutomationRunner::class)->runDue();
    }

    /**
     * Social Media Center: refreshes metrics, the inbox and expiring tokens.
     * Separate from publishing because it is hourly work, not per-minute work,
     * and it must never delay a scheduled post.
     */
    public function socialSync() {
        app(\App\Services\Social\SocialQueueRunner::class)->refreshExpiringTokens(180);
        app(\App\Services\Social\SocialAnalyticsService::class)->syncAll();
        app(\App\Services\Social\SocialInboxService::class)->syncAll();
    }

}
