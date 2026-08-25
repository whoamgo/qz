<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The /hi URL layer (Steps 10 & 12): deterministic URL-based locale, self
 * canonicals, reciprocal hreflang, and the i18n sitemap/robots. All read-only
 * HTTP — no data is created, so these are safe against any database.
 */
class MultilingualRoutingTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        // Pin the root URL so the suite is portable regardless of the local
        // APP_URL (which may carry a dev sub-path like /qz). This makes the test
        // client hit "/quizzes" and route()/canonical generate host-root URLs.
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
    }

    protected function tearDown(): void {
        app()->setLocale('en');
        parent::tearDown();
    }

    /* -------------------------------------------------- locale_route() helper */

    public function test_locale_route_returns_english_url_on_default_locale(): void {
        app()->setLocale('en');
        $this->assertStringEndsWith('/quizzes', locale_route('website.quizzes'));
    }

    public function test_locale_route_returns_hi_url_on_hi_locale(): void {
        app()->setLocale('hi');
        $this->assertStringContainsString('/hi/quizzes', locale_route('website.quizzes'));
    }

    public function test_locale_route_falls_back_to_english_when_no_twin(): void {
        app()->setLocale('hi');
        // Flow routes have no /hi twin → must not throw, returns the English route.
        $this->assertStringContainsString('/quizzes', locale_route('website.quizzes'));
        $this->assertTrue(Route::has('website.profile.index'));
        $this->assertFalse(Route::has('hi.website.profile.index')); // documents: no twin
    }

    /* ---------------------------------------------------- URL determinism */

    public function test_root_page_is_english(): void {
        $res = $this->get('/');
        $res->assertOk();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="en"/', $res->getContent());
    }

    public function test_hi_page_is_hindi(): void {
        $res = $this->get('/hi');
        $res->assertOk();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="hi"/', $res->getContent());
    }

    public function test_root_url_stays_english_even_with_hindi_session(): void {
        $res = $this->withSession(['lang' => 'hi'])->get('/quizzes');
        $res->assertOk();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="en"/', $res->getContent());
    }

    public function test_hi_url_stays_hindi_even_with_english_session(): void {
        $res = $this->withSession(['lang' => 'en'])->get('/hi/quizzes');
        $res->assertOk();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="hi"/', $res->getContent());
    }

    /* ------------------------------------------------------------ canonical */

    public function test_english_page_self_canonicalises(): void {
        $html = $this->get('/quizzes')->getContent();
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/quizzes"#', $html);
        $this->assertDoesNotMatchRegularExpression('#<link rel="canonical" href="[^"]*/hi/quizzes"#', $html);
    }

    public function test_hi_page_self_canonicalises_to_hi_url(): void {
        $html = $this->get('/hi/quizzes')->getContent();
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/hi/quizzes"#', $html);
    }

    /* ------------------------------------------------------------- hreflang */

    public function test_page_emits_reciprocal_hreflang(): void {
        $html = $this->get('/hi/quizzes')->getContent();
        $this->assertMatchesRegularExpression('#hreflang="en" href="[^"]*/quizzes"#', $html);
        $this->assertMatchesRegularExpression('#hreflang="hi" href="[^"]*/hi/quizzes"#', $html);
        $this->assertMatchesRegularExpression('#hreflang="x-default" href="[^"]*/quizzes"#', $html);
        // Exactly one set (en + hi + x-default), no spurious extras.
        $this->assertSame(3, substr_count($html, 'rel="alternate" hreflang='));
    }

    public function test_english_and_hi_twins_share_the_same_hreflang_set(): void {
        $en = $this->get('/quizzes')->getContent();
        $hi = $this->get('/hi/quizzes')->getContent();
        foreach (['hreflang="en" href="[^"]*/quizzes"',
                  'hreflang="hi" href="[^"]*/hi/quizzes"',
                  'hreflang="x-default" href="[^"]*/quizzes"'] as $needle) {
            $this->assertMatchesRegularExpression("#{$needle}#", $en);
            $this->assertMatchesRegularExpression("#{$needle}#", $hi);
        }
    }

    /* ----------------------------------------------------- sitemap + robots */

    public function test_sitemap_is_wellformed_and_lists_hi_urls_with_hreflang(): void {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap must be well-formed XML');
        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        $this->assertMatchesRegularExpression('#<loc>[^<]*/quizzes</loc>#', $xml);
        $this->assertMatchesRegularExpression('#<loc>[^<]*/hi/quizzes</loc>#', $xml);
        $this->assertStringContainsString('hreflang="hi"', $xml);
        $this->assertStringContainsString('hreflang="x-default"', $xml);
    }

    public function test_sitemap_entry_carries_full_alternate_set(): void {
        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertMatchesRegularExpression(
            '#<url>\s*<loc>[^<]*/quizzes</loc>(?:(?!</url>).)*?hreflang="en"(?:(?!</url>).)*?hreflang="hi"(?:(?!</url>).)*?hreflang="x-default"#s',
            $xml
        );
    }

    public function test_robots_disallows_hi_search(): void {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /hi/search', $robots);
        $this->assertStringContainsString('sitemap.xml', $robots);
    }
}
