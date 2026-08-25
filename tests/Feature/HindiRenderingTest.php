<?php

namespace Tests\Feature;

use App\Models\BankQuestion;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuizAttemptService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * End-to-end Hindi rendering (Steps 10 & 11). Reuses an existing published quiz,
 * temporarily gives it Hindi content inside a rolled-back transaction, and checks
 * that /hi/quiz/{slug} renders Hindi while /quiz/{slug} stays English — plus the
 * English fallback and the interactive quiz-play payload. Skips cleanly when the
 * database has no published quiz to exercise.
 */
class HindiRenderingTest extends TestCase {
    use DatabaseTransactions;

    private const HQ = 'हिन्दीप्रश्नTESTX';
    private const HO = 'हिन्दीविकल्पTESTX';
    private const H1 = 'हिन्दीशीर्षकTESTX';

    protected function setUp(): void {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
    }

    protected function tearDown(): void {
        app()->setLocale('en');
        parent::tearDown();
    }

    private function publishedQuiz(): ?Quiz {
        return Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->first();
    }

    /** Give a quiz + all its bank questions/options distinct Hindi content. */
    private function translate(Quiz $quiz): void {
        $quiz->title_hi = 'हिन्दी क्विज़ TESTX';
        $quiz->seo_h1_hi = self::H1;
        $quiz->save();

        $qids = DB::table('quiz_bank_question')->where('quiz_id', $quiz->id)->pluck('bank_question_id');
        foreach (BankQuestion::whereIn('id', $qids)->with('options')->get() as $bq) {
            $bq->question_text_hi = self::HQ . ' Q' . $bq->id;
            $bq->explanation_hi = 'हिन्दी व्याख्या';
            $bq->save();
            foreach ($bq->options as $i => $opt) {
                $opt->option_text_hi = self::HO . chr(65 + $i);
                $opt->save();
            }
        }
    }

    public function test_hi_quiz_page_renders_hindi_and_english_page_stays_english(): void {
        $quiz = $this->publishedQuiz();
        if (!$quiz) {
            $this->markTestSkipped('No published quiz with questions to render.');
        }
        $this->translate($quiz);

        $hi = $this->get('/hi/quiz/' . $quiz->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="hi"/', $hi);
        $this->assertStringContainsString(self::H1, $hi);                 // Hindi H1
        $this->assertStringContainsString(self::HQ, $hi);                 // Hindi sample question
        $this->assertStringContainsString(self::HO, $hi);                 // Hindi sample option

        $en = $this->get('/quiz/' . $quiz->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="en"/', $en);
        $this->assertStringNotContainsString(self::HQ, $en);              // no Hindi leak
        $this->assertStringNotContainsString(self::H1, $en);
    }

    public function test_hi_page_falls_back_to_english_when_no_translation(): void {
        $quiz = $this->publishedQuiz();
        if (!$quiz) {
            $this->markTestSkipped('No published quiz with questions to render.');
        }
        // Deliberately DO NOT translate — Hindi columns stay null.
        $hi = $this->get('/hi/quiz/' . $quiz->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*lang="hi"/', $hi);
        // Page must not be blank: the English title still shows.
        $this->assertStringContainsString(e($quiz->title), $hi);
    }

    public function test_hi_quiz_page_self_canonical_and_hreflang(): void {
        $quiz = $this->publishedQuiz();
        if (!$quiz) {
            $this->markTestSkipped('No published quiz with questions to render.');
        }
        $slug = preg_quote($quiz->slug, '#');
        $hi = $this->get('/hi/quiz/' . $quiz->slug)->getContent();
        $this->assertMatchesRegularExpression('#rel="canonical" href="[^"]*/hi/quiz/' . $slug . '"#', $hi);
        $this->assertMatchesRegularExpression('#hreflang="en" href="[^"]*/quiz/' . $slug . '"#', $hi);
        $this->assertMatchesRegularExpression('#hreflang="hi" href="[^"]*/hi/quiz/' . $slug . '"#', $hi);
    }

    /**
     * Regression guard (Step 14): publishedQuizzes() must eager-load category
     * name_hi, otherwise tr('name') silently falls back to English on /hi even
     * when a translation exists (a column-restricted eager load dropped it).
     */
    public function test_hi_quiz_page_shows_hindi_category_name(): void {
        $quiz = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
            ->whereNotNull('category_id')->with('category')->first();
        if (!$quiz || !$quiz->category) {
            $this->markTestSkipped('No published quiz with a category to render.');
        }
        $mark = 'हिन्दीश्रेणीCATTESTX';
        $quiz->category->name_hi = $mark;
        $quiz->category->save();

        $hi = $this->get('/hi/quiz/' . $quiz->slug)->assertOk()->getContent();
        $this->assertStringContainsString($mark, $hi);          // Hindi category name renders
        $en = $this->get('/quiz/' . $quiz->slug)->getContent();
        $this->assertStringNotContainsString($mark, $en);       // English page unaffected
    }

    public function test_interactive_attempt_payload_is_locale_aware(): void {
        $quiz = $this->publishedQuiz();
        $user = User::first();
        if (!$quiz || !$user) {
            $this->markTestSkipped('Need a published quiz and a user for an attempt.');
        }
        $this->translate($quiz);

        $svc = app(QuizAttemptService::class);
        $attempt = $svc->startOrResume($user, $quiz);

        app()->setLocale('hi');
        $hiPayload = $svc->questionsForAttempt($attempt);
        $this->assertStringContainsString(self::HQ, $hiPayload[0]['text']);
        $this->assertStringContainsString(self::HO, $hiPayload[0]['options'][0]['text']);

        app()->setLocale('en');
        $enPayload = $svc->questionsForAttempt($attempt);
        $this->assertStringNotContainsString(self::HQ, $enPayload[0]['text']);
    }
}
