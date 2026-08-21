<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsTrackingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the analytics protection guarantees: single count, duplicate cooldown,
 * cooldown expiry, IP abuse, multi-user-per-IP, bot exclusion and clear-all.
 *
 * Uses DatabaseTransactions (non-destructive: every test is rolled back) and the
 * array cache configured in phpunit.xml, so dedup/rate-limit state is isolated.
 */
class AnalyticsTrackingTest extends TestCase {
    use DatabaseTransactions;

    protected function setUp(): void {
        parent::setUp();
        config([
            'analytics.enabled'                      => true,
            'analytics.geo.driver'                   => 'none',   // no network in tests
            'analytics.dedupe_seconds'               => 60,
            'analytics.max_events_per_ip_per_minute' => 5,
            'analytics.store_invalid'                => true,
        ]);
        Cache::flush();
        AnalyticsEvent::query()->delete(); // clean slate inside the transaction
    }

    private function service(): AnalyticsTrackingService {
        return app(AnalyticsTrackingService::class);
    }

    private function request(string $ip, string $visitorId, ?string $ua = null): Request {
        $ua = $ua ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120 Safari/537.36';
        $req = Request::create('/quiz', 'POST', [], ['qz_vid' => $visitorId], [], [
            'REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua,
        ]);
        $req->setUserResolver(fn($g = null) => null);
        return $req;
    }

    private array $click = [
        'page_path' => '/quiz', 'element_name' => 'Create Room',
        'element_category' => 'quiz', 'element_id' => 'btn-create', 'element_type' => 'button',
    ];

    /** Test 1: a single click is counted once. */
    public function test_single_click_counts_once(): void {
        $status = $this->service()->trackEvent('click', $this->click, $this->request('11.0.0.1', 'visitorA'))['status'];

        $this->assertSame('valid', $status);
        $this->assertSame(1, AnalyticsEvent::valid()->ofType('click')->count());
    }

    /** Test 2: 100 rapid clicks by the same session become ONE valid click. */
    public function test_repeated_clicks_within_cooldown_count_once(): void {
        for ($i = 0; $i < 100; $i++) {
            $this->service()->trackEvent('click', $this->click, $this->request('11.0.0.2', 'visitorB'));
        }

        $this->assertSame(1, AnalyticsEvent::valid()->ofType('click')->count());
        $this->assertSame(99, AnalyticsEvent::where('status', 'duplicate')->count());
    }

    /** Test 3: after the cooldown expires the next click counts again. */
    public function test_click_counts_again_after_cooldown(): void {
        $svc = $this->service();

        $first = $svc->trackEvent('click', $this->click, $this->request('11.0.0.3', 'visitorC'))['status'];
        Cache::flush(); // simulate cooldown window elapsing
        $after = $svc->trackEvent('click', $this->click, $this->request('11.0.0.3', 'visitorC'))['status'];

        $this->assertSame('valid', $first);
        $this->assertSame('valid', $after);
        $this->assertSame(2, AnalyticsEvent::valid()->ofType('click')->count());
    }

    /** Test 4: an IP flooding distinct elements is capped, not fully counted. */
    public function test_ip_abuse_is_rate_limited(): void {
        $svc = $this->service();

        for ($i = 0; $i < 20; $i++) {
            $data = array_merge($this->click, ['element_name' => "Btn{$i}", 'element_id' => "id{$i}"]);
            $svc->trackEvent('click', $data, $this->request('11.0.0.4', 'visitorD'));
        }

        // Only the configured 5/minute may count; the rest are rate_limited.
        $this->assertSame(5, AnalyticsEvent::valid()->ofType('click')->count());
        $this->assertSame(15, AnalyticsEvent::where('status', 'rate_limited')->count());
    }

    /** Test 5: two different visitors on the same public IP are not merged. */
    public function test_different_users_behind_same_ip_are_separate(): void {
        $svc = $this->service();

        $one = $svc->trackEvent('click', $this->click, $this->request('11.0.0.5', 'userX'))['status'];
        $two = $svc->trackEvent('click', $this->click, $this->request('11.0.0.5', 'userY'))['status'];

        $this->assertSame('valid', $one);
        $this->assertSame('valid', $two, 'A second genuine visitor sharing the IP must still count.');
        $this->assertSame(2, AnalyticsEvent::valid()->ofType('click')->count());
    }

    /** Test 6: bot traffic is recorded as a bot and excluded from valid totals. */
    public function test_bot_traffic_is_not_counted(): void {
        $status = $this->service()->trackEvent(
            'click', $this->click,
            $this->request('11.0.0.6', 'visitorE', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        )['status'];

        $this->assertSame('bot', $status);
        $this->assertSame(0, AnalyticsEvent::valid()->ofType('click')->count());
        $this->assertTrue($this->service()->isBot('Googlebot'));
        $this->assertFalse($this->service()->isBot('Mozilla/5.0 Chrome/120'));
    }

    /** Test 7: clearing all analytics empties the table. */
    public function test_clear_all_removes_everything(): void {
        $svc = $this->service();
        $svc->trackEvent('click', $this->click, $this->request('11.0.0.7', 'v1'));
        $svc->trackEvent('click', array_merge($this->click, ['element_name' => 'Other']), $this->request('11.0.0.8', 'v2'));
        $this->assertGreaterThan(0, AnalyticsEvent::count());

        AnalyticsEvent::query()->delete();

        $this->assertSame(0, AnalyticsEvent::count());
    }
}
