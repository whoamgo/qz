<?php

namespace Tests\Feature;

use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use Tests\TestCase;

/**
 * Read-only tests for App\Traits\HasTranslations::tr(). Uses in-memory model
 * instances (no DB writes) — the trait is a pure attribute reader.
 */
class HasTranslationsTest extends TestCase {
    protected function tearDown(): void {
        app()->setLocale('en');
        parent::tearDown();
    }

    private function quiz(): Quiz {
        $q = new Quiz();
        $q->title = 'Daily Current Affairs Quiz';
        $q->slug  = 'daily-ca'; // slug is NOT translatable
        return $q;
    }

    /** en → base English field. */
    public function test_english_locale_returns_base_field(): void {
        app()->setLocale('en');
        $q = $this->quiz();
        $q->title_hi = 'हिंदी शीर्षक';
        $this->assertSame('Daily Current Affairs Quiz', $q->tr('title'));
    }

    /** hi + translation present → Hindi value. */
    public function test_hindi_returns_translation_when_present(): void {
        app()->setLocale('hi');
        $q = $this->quiz();
        $q->title_hi = 'दैनिक करेंट अफेयर्स क्विज़';
        $this->assertSame('दैनिक करेंट अफेयर्स क्विज़', $q->tr('title'));
    }

    /** hi + NULL translation → English fallback. */
    public function test_hindi_null_falls_back_to_english(): void {
        app()->setLocale('hi');
        $q = $this->quiz();
        $q->title_hi = null;
        $this->assertSame('Daily Current Affairs Quiz', $q->tr('title'));
    }

    /** hi + empty-string translation → English fallback. */
    public function test_hindi_empty_string_falls_back_to_english(): void {
        app()->setLocale('hi');
        $q = $this->quiz();
        $q->title_hi = '';
        $this->assertSame('Daily Current Affairs Quiz', $q->tr('title'));
    }

    /** A field NOT configured for translation must never read a "_hi" column. */
    public function test_unknown_field_never_reads_a_hi_column(): void {
        app()->setLocale('hi');
        $q = $this->quiz();
        $q->slug_hi = 'should-be-ignored'; // not in $translatable
        $this->assertSame('daily-ca', $q->tr('slug'));
    }

    /** All four models translate their configured fields. */
    public function test_all_four_models_translate(): void {
        app()->setLocale('hi');

        $c = new Category();       $c->name = 'Current Affairs';     $c->name_hi = 'करेंट अफेयर्स';
        $bq = new BankQuestion();  $bq->question_text = 'Who is the President of India?';
                                   $bq->question_text_hi = 'भारत के राष्ट्रपति कौन हैं?';
        $bo = new BankOption();    $bo->option_text = 'Droupadi Murmu'; $bo->option_text_hi = 'द्रौपदी मुर्मू';

        $this->assertSame('करेंट अफेयर्स', $c->tr('name'));
        $this->assertSame('भारत के राष्ट्रपति कौन हैं?', $bq->tr('question_text'));
        $this->assertSame('द्रौपदी मुर्मू', $bo->tr('option_text'));
    }

    /** hasTranslation() / translationComplete() helpers. */
    public function test_translation_status_helpers(): void {
        app()->setLocale('hi');
        $c = new Category();
        $c->name = 'X';
        $this->assertFalse($c->hasTranslation('name'));      // no name_hi yet
        $c->name_hi = 'क्ष';
        $this->assertTrue($c->hasTranslation('name'));
        $this->assertFalse($c->translationComplete());       // other fields still missing
        // Default locale is always "present".
        $this->assertTrue($c->hasTranslation('name', 'en'));
    }

    /** tr() is read-only: no attribute is created or mutated. */
    public function test_read_only_does_not_mutate(): void {
        app()->setLocale('hi');
        $q = new Quiz();
        $q->title = 'English Only';
        $result = $q->tr('title');
        $this->assertSame('English Only', $result);
        $this->assertArrayNotHasKey('title_hi', $q->getAttributes()); // tr() didn't create it
        $this->assertSame('English Only', $q->title);                 // base unchanged
    }
}
