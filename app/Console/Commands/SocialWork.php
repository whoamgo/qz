<?php

namespace App\Console\Commands;

use App\Services\Social\SocialQueueRunner;
use Illuminate\Console\Command;

/**
 * Drains the social publishing queue.
 *
 * Run it from the scheduler (every minute) or from supervisor with --daemon.
 * Overlapping runs are safe - jobs are claimed with a lease - so a slow run
 * never needs a lock file.
 */
class SocialWork extends Command {

    protected $signature = 'social:work
                            {--seconds=240 : Soft time budget for this run}
                            {--jobs=25 : Maximum jobs to process in this run}
                            {--daemon : Keep running, sleeping between passes}
                            {--sleep=10 : Seconds to sleep between passes in daemon mode}';

    protected $description = 'Process queued social media publishing jobs';

    public function handle(SocialQueueRunner $runner): int {
        $seconds = (int) $this->option('seconds');
        $jobs    = (int) $this->option('jobs');

        if (!$this->option('daemon')) {
            $this->report($runner->run($seconds, $jobs));
            return self::SUCCESS;
        }

        $this->info('Social publishing worker started. Press Ctrl+C to stop.');

        while (true) {
            $stats = $runner->run($seconds, $jobs);

            if ($stats['processed'] > 0) {
                $this->report($stats);
            }

            sleep(max(1, (int) $this->option('sleep')));
        }
    }

    protected function report(array $stats): void {
        if ($stats['processed'] === 0) {
            $this->line('Nothing due.');
            return;
        }

        $this->info(sprintf(
            'Processed %d job(s): %d published, %d failed, %d awaiting retry, %d deferred.',
            $stats['processed'],
            $stats['published'],
            $stats['failed'],
            $stats['retrying'],
            $stats['skipped']
        ));
    }
}
