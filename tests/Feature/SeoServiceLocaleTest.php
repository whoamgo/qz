<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Quiz;
use App\Services\SeoService;
use Tests\TestCase;

/**
 * SeoService locale-awareness (Step 10). Pure in-memory model instances — no DB —
 * so these run everywhere. Verifies that the meta/content payload is Hindi under
 * the "hi" locale, falls back to English when a Hindi field is blank, and that the
 * "fill missing defaults" writers ALWAYS generate English (never Hindi) so the
 * base English SEO columns can never be polluted.
 */
class SeoServiceLocaleTest extends TestCase {
    private SeoService $seo;

    protected function setUp(): void {
        parent::setUp();
        $this->seo = new SeoService();
    }

    protected function tearDown(): void {
        app()->setLocale('en');
        parent::tearDown();
    }

    private function category(): Category {
        $c = new Category();
        $c->name               = 'General Knowledge';
        $c->name_hi            = 'सामान्य ज्ञान';
        $c->meta_title         = 'GK Quiz';
        $c->meta_title_hi      = 'जीके क्विज़';
        $c->meta_description   = 'English description';
        $c->meta_description_hi = 'हिंदी विवरण';
        $c->meta_keywords      = 'gk, quiz';
        $c->meta_keywords_hi   = 'जीके, क्विज़';
        $c->seo_h1             = 'GK Heading';
        $c->seo_h1_hi          = 'जीके शीर्षक';
        $c->seo_intro          = 'Intro EN';
        $c->seo_intro_hi       = 'परिचय';
        return $c;
    }

    private function quiz(): Quiz {
        $q = new Quiz();
        $q->title            = 'HTML Quiz';
        $q->title_hi         = 'एचटीएमएल क्विज़';
        $q->meta_title       = 'HTML Quiz Meta';
        $q->meta_title_hi    = 'एचटीएमएल मेटा';
        $q->description      = 'English quiz desc';
        $q->description_hi   = 'हिंदी क्विज़ विवरण';
        $q->seo_h1           = 'HTML H1';
        $q->seo_h1_hi        = 'एचटीएमएल H1';
        return $q;
    }

    public function test_category_meta_is_english_under_en_locale(): void {
        app()->setLocale('en');
        $meta = $this->seo->categoryMeta($this->category(), []);
        $this->assertSame('GK Quiz', $meta['title']);
        $this->assertSame('English description', $meta['description']);
        $this->assertSame('gk, quiz', $meta['keywords']);
    }

    public function test_category_meta_is_hindi_under_hi_locale(): void {
        app()->setLocale('hi');
        $meta = $this->seo->categoryMeta($this->category(), []);
        $this->assertSame('जीके क्विज़', $meta['title']);
        $this->assertSame('हिंदी विवरण', $meta['description']);
        $this->assertSame('जीके, क्विज़', $meta['keywords']);
    }

    public function test_category_content_h1_and_intro_follow_locale(): void {
        app()->setLocale('hi');
        $content = $this->seo->categoryContent($this->category(), []);
        $this->assertSame('जीके शीर्षक', $content['h1']);
        $this->assertSame('परिचय', $content['intro']);
    }

    public function test_hindi_falls_back_to_english_when_blank(): void {
        app()->setLocale('hi');
        $c = $this->category();
        $c->meta_title_hi = null;   // no Hindi meta title
        $c->seo_h1_hi     = '';     // empty Hindi H1
        $meta    = $this->seo->categoryMeta($c, []);
        $content = $this->seo->categoryContent($c, []);
        $this->assertSame('GK Quiz', $meta['title']);      // English fallback
        $this->assertSame('GK Heading', $content['h1']);   // English fallback
    }

    public function test_generated_fallback_title_uses_hindi_name_on_hi(): void {
        app()->setLocale('hi');
        $c = new Category();
        $c->name = 'General Knowledge';
        $c->name_hi = 'सामान्य ज्ञान';
        // No meta_title at all → SeoService generates one, and on /hi it uses the Hindi name.
        $meta = $this->seo->categoryMeta($c, []);
        $this->assertStringContainsString('सामान्य ज्ञान', $meta['title']);
    }

    public function test_quiz_meta_and_content_follow_locale(): void {
        app()->setLocale('hi');
        $meta    = $this->seo->quizMeta($this->quiz(), []);
        $content = $this->seo->quizContent($this->quiz());
        $this->assertSame('एचटीएमएल मेटा', $meta['title']);
        $this->assertSame('एचटीएमएल H1', $content['h1']);
    }

    public function test_fill_category_defaults_writes_english_even_under_hi_locale(): void {
        app()->setLocale('hi'); // simulate an admin whose session is Hindi
        $c = new Category();
        $c->name = 'General Knowledge';
        $c->name_hi = 'सामान्य ज्ञान';
        // meta_title/description/seo_h1 are blank → fill them.
        $this->seo->fillCategoryDefaults($c, 5, 100);
        // The generated ENGLISH columns must use the English name, never the Hindi one.
        $this->assertStringContainsString('General Knowledge', $c->meta_title);
        $this->assertStringNotContainsString('सामान्य ज्ञान', $c->meta_title);
        $this->assertStringContainsString('General Knowledge', (string) $c->seo_h1);
    }

    public function test_fill_quiz_defaults_writes_english_even_under_hi_locale(): void {
        app()->setLocale('hi');
        $q = new Quiz();
        $q->title = 'HTML Quiz';
        $q->title_hi = 'एचटीएमएल क्विज़';
        $q->difficulty = 'easy';
        $this->seo->fillQuizDefaults($q, 10);
        $this->assertStringContainsString('HTML Quiz', $q->meta_title);
        $this->assertStringNotContainsString('एचटीएमएल', $q->meta_title);
    }
}
