<?php

namespace App\Console\Commands;

use App\Services\Social\SocialAnalyticsService;
use App\Services\Social\SocialInboxService;
use App\Services\Social\SocialQueueRunner;
use Illuminate\Console\Command;

/** Refreshes analytics, the inbox and expiring tokens. Intended to run hourly. */
class SocialSync extends Command {

    protected $signature = 'social:sync
                            {--analytics : Only sync analytics}
                            {--inbox : Only sync the inbox}
                            {--tokens : Only refresh tokens}';

    protected $description = 'Sync social analytics, inbox and access tokens';

    public function handle(
        SocialAnalyticsService $analytics,
        SocialInboxService $inbox,
        SocialQueueRunner $runner
    ): int {
        $all = !$this->option('analytics') && !$this->option('inbox') && !$this->option('tokens');

        if ($all || $this->option('tokens')) {
            $this->info('Refreshed ' . $runner->refreshExpiringTokens(180) . ' access token(s).');
        }

        if ($all || $this->option('analytics')) {
            $stats = $analytics->syncAll();
            $this->info("Analytics: {$stats['accounts']} account(s), {$stats['posts']} post(s), {$stats['errors']} error(s).");
        }

        if ($all || $this->option('inbox')) {
            $stats = $inbox->syncAll();
            $this->info("Inbox: {$stats['synced']} new item(s) from {$stats['accounts']} account(s).");
        }

        return self::SUCCESS;
    }
}
