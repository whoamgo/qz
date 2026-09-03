<?php

namespace App\Console\Commands;

use App\Services\Social\SocialAutomationRunner;
use Illuminate\Console\Command;

/** Fires any social automation whose next run time has passed. */
class SocialAutomations extends Command {

    protected $signature = 'social:automations';
    protected $description = 'Run due social media automations';

    public function handle(SocialAutomationRunner $runner): int {
        $stats = $runner->runDue();

        $this->info(sprintf(
            '%d automation(s) ran: %d post(s) created, %d published, %d skipped, %d error(s).',
            $stats['ran'],
            $stats['created'],
            $stats['published'],
            $stats['skipped'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }
}
