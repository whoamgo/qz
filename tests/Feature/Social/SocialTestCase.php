<?php

namespace Tests\Feature\Social;

use App\Constants\SocialStatus as S;
use App\Models\Admin;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialSetting;
use App\Services\Social\Adapters\FacebookAdapter;
use App\Services\Social\Adapters\TelegramAdapter;
use App\Services\Social\SocialPublisher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared setup for the Social Media Center tests.
 *
 * Every test runs inside a transaction against the configured database and is
 * rolled back, so the suite is non-destructive on a real install.
 */
abstract class SocialTestCase extends TestCase {

    use DatabaseTransactions;

    protected FakeAdapter $telegram;
    protected FakeAdapter $facebook;

    protected function setUp(): void {
        parent::setUp();

        SocialSetting::flush();

        $settings = SocialSetting::config();
        $settings->fill([
            'enabled'            => true,
            'require_approval'   => false,
            'max_attempts'       => 3,
            'retry_base_seconds' => 60,
            'job_lease_seconds'  => 600,
            'utm_enabled'        => false,
        ])->save();
        SocialSetting::flush();

        // Two independent fakes so "one platform succeeded, the other failed"
        // is expressible.
        $this->telegram = new FakeAdapter();
        $this->facebook = new FakeAdapter();

        $this->app->instance(TelegramAdapter::class, $this->telegram);
        $this->app->instance(FacebookAdapter::class, $this->facebook);
    }

    protected function admin(string $role = S::ROLE_SUPER_ADMIN): Admin {
        $admin = Admin::first();

        if (!$admin) {
            $admin = new Admin();
            $admin->name     = 'Test Admin';
            $admin->email    = 'social-test-' . Str::random(6) . '@example.test';
            $admin->username = 'socialtest' . Str::random(6);
            $admin->password = bcrypt('secret-password');
        }

        $admin->role = $role;
        $admin->save();

        return $admin;
    }

    protected function account(string $platform = S::TELEGRAM): SocialAccount {
        $account = new SocialAccount([
            'platform'    => $platform,
            'name'        => ucfirst($platform) . ' Test',
            'external_id' => $platform . '-' . Str::random(8),
            'status'      => S::ACCOUNT_CONNECTED,
            'is_default'  => true,
        ]);

        $account->setCredentials([
            'access_token' => 'test-token-' . Str::random(10),
            'bot_token'    => '123456:test-bot-token',
            'chat_id'      => '@testchannel',
            'page_id'      => '1234567890',
            'page_access_token' => 'page-token-' . Str::random(8),
        ]);

        $account->save();

        return $account;
    }

    /** A draft post with targets already created for the given platforms. */
    protected function post(array $platforms = [S::TELEGRAM], array $attributes = []): SocialPost {
        foreach ($platforms as $platform) {
            if (!SocialAccount::platform($platform)->connected()->exists()) {
                $this->account($platform);
            }
        }

        $post = SocialPost::create(array_merge([
            'title'        => 'Test post',
            'caption'      => 'Can you answer this GK question?',
            'hashtags'     => '#gk #quiz',
            'content_type' => S::CONTENT_TEXT,
            'status'       => S::DRAFT,
            'language'     => 'english',
            'utm_enabled'  => false,
        ], $attributes));

        app(SocialPublisher::class)->syncTargets($post, $platforms);

        return $post->fresh('targets');
    }

    protected function publisher(): SocialPublisher {
        return app(SocialPublisher::class);
    }
}
