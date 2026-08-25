<?php

namespace Tests\Feature;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuestionImport;
use App\Models\QuestionImportRow;
use App\Models\QuizImport;
use App\Models\QuizImportRow;
use App\Services\QuestionImportService;
use App\Services\QuizImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The bilingual CSV importers (Steps 9 & 9b): staged Hindi content is promoted to
 * the _hi columns, English-only imports leave them null, and the optional Hindi
 * columns live at the END of each HEADERS list. Every write is wrapped in a
 * transaction and rolled back — nothing is persisted.
 */
class BilingualImportTest extends TestCase {
    use DatabaseTransactions;

    /** A usable [parent, subId, subName] for a valid staged row. */
    private function category(): ?array {
        $parent = Category::whereNull('parent_id')->where('status', 1)->first();
        if (!$parent) {
            return null;
        }
        $sub = Category::where('parent_id', $parent->id)->where('status', 1)->first();
        return [$parent, $sub?->id, $sub?->name];
    }

    /* --------------------------------------------------- HEADERS structure */

    public function test_question_headers_end_with_optional_hindi_columns(): void {
        $tail = array_slice(QuestionImportService::HEADERS, -6);
        $this->assertSame(
            ['question_hi', 'option_a_hi', 'option_b_hi', 'option_c_hi', 'option_d_hi', 'explanation_hi'],
            $tail
        );
        // The required headers must NOT include any Hindi column (backward compatible).
        foreach (QuestionImportService::REQUIRED_HEADERS as $h) {
            $this->assertStringEndsNotWith('_hi', $h);
        }
    }

    public function test_quiz_headers_end_with_optional_hindi_columns(): void {
        $tail = array_slice(QuizImportService::HEADERS, -8);
        $this->assertSame(
            ['quiz_title_hi', 'quiz_description_hi', 'question_hi',
             'option_a_hi', 'option_b_hi', 'option_c_hi', 'option_d_hi', 'explanation_hi'],
            $tail
        );
        foreach (QuizImportService::REQUIRED_HEADERS as $h) {
            $this->assertStringEndsNotWith('_hi', $h);
        }
    }

    /* ------------------------------------------- question importer promotion */

    public function test_question_import_promotes_hindi_to_bank(): void {
        [$parent] = $this->category() ?? [null];
        if (!$parent) {
            $this->markTestSkipped('No categories in the database to import against.');
        }

        $import = new QuestionImport();
        foreach (['admin_id' => 1, 'file_name' => 't.csv', 'stored_file' => 't.csv', 'file_type' => 'csv',
                  'status' => QuestionImport::STATUS_READY_FOR_REVIEW, 'total_records' => 1, 'processed_records' => 1,
                  'valid_records' => 1, 'invalid_records' => 0, 'duplicate_records' => 0, 'failed_records' => 0,
                  'imported_records' => 0, 'file_cursor' => 0] as $k => $v) {
            $import->{$k} = $v;
        }
        $import->save();

        $row = new QuestionImportRow();
        foreach ([
            'import_id' => $import->id, 'row_number' => 2, 'category_name' => $parent->name, 'category_id' => $parent->id,
            'question' => 'Which planet is known as the Red Planet here?', 'question_type' => 'mcq_single', 'difficulty' => 'easy',
            'option_a' => 'Mars', 'option_b' => 'Venus', 'option_c' => 'Jupiter', 'option_d' => 'Saturn', 'correct_answer' => 'A',
            'explanation' => 'Mars is the Red Planet.', 'validation_status' => QuestionImportRow::STATUS_VALID,
            'question_hi' => 'कौन सा ग्रह लाल ग्रह के रूप में जाना जाता है?', 'option_a_hi' => 'मंगल', 'option_b_hi' => 'शुक्र',
            'option_c_hi' => 'बृहस्पति', 'option_d_hi' => 'शनि', 'explanation_hi' => 'मंगल लाल ग्रह है।',
        ] as $k => $v) {
            $row->{$k} = $v;
        }
        $row->save();

        app(QuestionImportService::class)->approve($import->fresh());

        $bq = BankQuestion::where('question_text', 'Which planet is known as the Red Planet here?')
            ->with('options')->latest('id')->first();

        $this->assertNotNull($bq);
        $this->assertSame('कौन सा ग्रह लाल ग्रह के रूप में जाना जाता है?', $bq->question_text_hi);
        $this->assertSame('मंगल लाल ग्रह है।', $bq->explanation_hi);
        $this->assertSame('मंगल', $bq->options->firstWhere('option_text', 'Mars')->option_text_hi);
        $this->assertTrue((bool) $bq->options->firstWhere('option_text', 'Mars')->is_correct);
    }

    public function test_question_import_english_only_leaves_hindi_null(): void {
        [$parent] = $this->category() ?? [null];
        if (!$parent) {
            $this->markTestSkipped('No categories in the database to import against.');
        }

        $import = new QuestionImport();
        foreach (['admin_id' => 1, 'file_name' => 't.csv', 'stored_file' => 't.csv', 'file_type' => 'csv',
                  'status' => QuestionImport::STATUS_READY_FOR_REVIEW, 'total_records' => 1, 'processed_records' => 1,
                  'valid_records' => 1, 'invalid_records' => 0, 'duplicate_records' => 0, 'failed_records' => 0,
                  'imported_records' => 0, 'file_cursor' => 0] as $k => $v) {
            $import->{$k} = $v;
        }
        $import->save();

        $row = new QuestionImportRow();
        foreach ([
            'import_id' => $import->id, 'row_number' => 2, 'category_name' => $parent->name, 'category_id' => $parent->id,
            'question' => 'English only question about basic maths?', 'question_type' => 'mcq_single', 'difficulty' => 'easy',
            'option_a' => '4', 'option_b' => '5', 'option_c' => '6', 'option_d' => '7', 'correct_answer' => 'A',
            'validation_status' => QuestionImportRow::STATUS_VALID,
        ] as $k => $v) {
            $row->{$k} = $v;
        }
        $row->save();

        app(QuestionImportService::class)->approve($import->fresh());

        $bq = BankQuestion::where('question_text', 'English only question about basic maths?')
            ->with('options')->latest('id')->first();
        $this->assertNotNull($bq);
        $this->assertNull($bq->question_text_hi);
        $this->assertNull($bq->options->first()->option_text_hi);
    }

    /* ----------------------------------------------- quiz importer promotion */

    public function test_quiz_import_promotes_hindi_to_quiz_and_bank(): void {
        $cat = $this->category();
        if (!$cat) {
            $this->markTestSkipped('No categories in the database to import against.');
        }
        [$parent, $subId, $subName] = $cat;

        $import = $this->quizImport();

        $row = new QuizImportRow();
        foreach ([
            'import_id' => $import->id, 'row_number' => 2, 'quiz_key' => 'bilingual-import-test',
            'quiz_title' => 'Bilingual Import Test Quiz', 'quiz_slug' => 'bilingual-import-test',
            'quiz_description' => 'English desc', 'category_raw' => $parent->name, 'category_id' => $parent->id,
            'sub_category_raw' => $subName, 'sub_category_id' => $subId,
            'question' => 'Which planet is the Red Planet in our system?', 'question_type' => 'mcq_single', 'question_difficulty' => 'easy',
            'option_a' => 'Mars', 'option_b' => 'Venus', 'option_c' => 'Jupiter', 'option_d' => 'Saturn', 'correct_answer' => 'A',
            'explanation' => 'Mars is the Red Planet.', 'validation_status' => QuizImportRow::STATUS_VALID,
            'quiz_title_hi' => 'द्विभाषी आयात परीक्षण क्विज़', 'quiz_description_hi' => 'हिंदी विवरण',
            'question_hi' => 'हमारे सिस्टम में लाल ग्रह कौन सा है?', 'option_a_hi' => 'मंगल', 'option_b_hi' => 'शुक्र',
            'option_c_hi' => 'बृहस्पति', 'option_d_hi' => 'शनि', 'explanation_hi' => 'मंगल लाल ग्रह है।',
        ] as $k => $v) {
            $row->{$k} = $v;
        }
        $row->save();

        app(QuizImportService::class)->approve($import->fresh());

        $quiz = Quiz::where('title', 'Bilingual Import Test Quiz')->latest('id')->first();
        $this->assertNotNull($quiz);
        $this->assertSame('द्विभाषी आयात परीक्षण क्विज़', $quiz->title_hi);
        $this->assertSame('हिंदी विवरण', $quiz->description_hi);

        $bq = BankQuestion::where('question_text', 'Which planet is the Red Planet in our system?')
            ->with('options')->latest('id')->first();
        $this->assertNotNull($bq);
        $this->assertSame('हमारे सिस्टम में लाल ग्रह कौन सा है?', $bq->question_text_hi);
        $this->assertSame('मंगल लाल ग्रह है।', $bq->explanation_hi);
        $this->assertSame('मंगल', $bq->options->firstWhere('option_text', 'Mars')->option_text_hi);
    }

    public function test_quiz_import_english_only_leaves_hindi_null(): void {
        $cat = $this->category();
        if (!$cat) {
            $this->markTestSkipped('No categories in the database to import against.');
        }
        [$parent, $subId, $subName] = $cat;

        $import = $this->quizImport();

        $row = new QuizImportRow();
        foreach ([
            'import_id' => $import->id, 'row_number' => 2, 'quiz_key' => 'english-only-import-test',
            'quiz_title' => 'English Only Import Quiz', 'quiz_slug' => 'english-only-import-test',
            'quiz_description' => 'English only', 'category_raw' => $parent->name, 'category_id' => $parent->id,
            'sub_category_raw' => $subName, 'sub_category_id' => $subId,
            'question' => 'What is two plus two in english only?', 'question_type' => 'mcq_single', 'question_difficulty' => 'easy',
            'option_a' => '4', 'option_b' => '5', 'option_c' => '6', 'option_d' => '7', 'correct_answer' => 'A',
            'validation_status' => QuizImportRow::STATUS_VALID,
        ] as $k => $v) {
            $row->{$k} = $v;
        }
        $row->save();

        app(QuizImportService::class)->approve($import->fresh());

        $quiz = Quiz::where('title', 'English Only Import Quiz')->latest('id')->first();
        $this->assertNotNull($quiz);
        $this->assertNull($quiz->title_hi);
        $this->assertNull($quiz->description_hi);

        $bq = BankQuestion::where('question_text', 'What is two plus two in english only?')
            ->with('options')->latest('id')->first();
        $this->assertNull($bq->question_text_hi);
        $this->assertNull($bq->options->first()->option_text_hi);
    }

    private function quizImport(): QuizImport {
        $import = new QuizImport();
        foreach (['admin_id' => 1, 'file_name' => 't.csv', 'stored_file' => 't.csv', 'file_type' => 'csv',
                  'status' => QuizImport::STATUS_READY_FOR_REVIEW, 'total_records' => 1, 'processed_records' => 1,
                  'valid_records' => 1, 'invalid_records' => 0, 'duplicate_records' => 0, 'failed_records' => 0,
                  'total_quizzes' => 1, 'valid_quizzes' => 1, 'imported_quizzes' => 0, 'imported_questions' => 0,
                  'file_cursor' => 0] as $k => $v) {
            $import->{$k} = $v;
        }
        $import->save();
        return $import;
    }
}
