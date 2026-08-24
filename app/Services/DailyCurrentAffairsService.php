<?php

namespace App\Services;

use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-click "Daily Current Affairs" publishing. Parses a block of pasted MCQs
 * and, in a single transaction, creates the bank questions + options, a dated
 * quiz filed under Current Affairs → Daily Current Affairs, attaches the
 * questions, and fills date-based SEO. Turns a ~20-minute job into ~2 minutes.
 */
class DailyCurrentAffairsService {
    const PARENT_SLUG = 'current-affairs';
    const DAILY_SUB_SLUGS = ['daily-current-affairs', 'today-current-affairs-quiz'];

    /**
     * Parses pasted MCQs into a normalised structure.
     *
     * Format (blank line between questions):
     *   1. Question text?
     *   A) Option one
     *   B) Option two *          <- '*' marks the correct answer
     *   C) Option three
     *   D) Option four
     *   Explanation: why B is correct   (optional)
     *
     * Correct answer may instead be given on its own line: "Answer: B".
     *
     * @return array{questions: array, errors: array}
     */
    public function parse(string $raw): array {
        $blocks = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $raw)));
        $questions = [];
        $errors = [];

        foreach ($blocks as $i => $block) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\n/', trim($block))), fn($l) => $l !== ''));
            if (!$lines) {
                continue;
            }

            $q = ['text' => null, 'options' => [], 'explanation' => null, 'answer' => null];

            foreach ($lines as $line) {
                if (preg_match('/^(explanation|exp|sol|solution)\s*[:\-]\s*(.*)$/i', $line, $m)) {
                    $q['explanation'] = trim($m[2]);
                    continue;
                }
                if (preg_match('/^(answer|ans|correct)\s*[:\-]\s*\(?([a-z])\)?/i', $line, $m)) {
                    $q['answer'] = strtoupper($m[2]);
                    continue;
                }
                if (preg_match('/^\(?([a-z])\)?[\.\)]\s+(.*)$/i', $line, $m)) {
                    $text = trim($m[2]);
                    $correct = false;
                    if (preg_match('/\*+\s*$/', $text)) {
                        $correct = true;
                        $text = trim(preg_replace('/\s*\*+\s*$/', '', $text));
                    }
                    $q['options'][strtoupper($m[1])] = ['text' => $text, 'correct' => $correct];
                    continue;
                }
                // Question text — first non-option line; strip leading numbering.
                if ($q['text'] === null) {
                    $q['text'] = trim(preg_replace('/^\s*(q\s*\d*|\d+)\s*[\.\)\:]\s*/i', '', $line));
                } elseif (empty($q['options'])) {
                    $q['text'] .= ' ' . $line; // multi-line question stem
                }
            }

            $label = 'Q' . ($i + 1);
            if (blank($q['text'])) {
                $errors[] = "$label: no question text found.";
                continue;
            }
            if (count($q['options']) < 2) {
                $errors[] = "$label (\"" . Str::limit($q['text'], 45) . "\"): needs at least 2 options (A) …, B) …).";
                continue;
            }
            $correct = $q['answer'];
            if (!$correct) {
                foreach ($q['options'] as $letter => $o) {
                    if ($o['correct']) { $correct = $letter; break; }
                }
            }
            if (!$correct || !isset($q['options'][$correct])) {
                $errors[] = "$label (\"" . Str::limit($q['text'], 45) . "\"): mark the correct option with '*' or add an 'Answer: B' line.";
                continue;
            }
            $q['correct'] = $correct;
            $questions[] = $q;
        }

        return ['questions' => $questions, 'errors' => $errors];
    }

    /** The slug a daily quiz for the given date will use (for collision checks). */
    public function slugForDate(Carbon $date): string {
        return slug('Daily Current Affairs Quiz ' . $date->format('d F Y'));
    }

    public function existingForDate(Carbon $date): ?Quiz {
        return Quiz::where('slug', $this->slugForDate($date))->first();
    }

    /**
     * Creates the dated quiz + bank questions in one transaction.
     *
     * @param array $parsed  output of parse()['questions']
     * @param array $opts    ['difficulty','time_limit','pass_percentage','publish']
     */
    public function create(Carbon $date, array $parsed, array $opts = []): Quiz {
        $parent = Category::whereNull('parent_id')->where('slug', self::PARENT_SLUG)->first();
        $sub    = $parent
            ? Category::where('parent_id', $parent->id)->whereIn('slug', self::DAILY_SUB_SLUGS)->orderByRaw("FIELD(slug, '" . implode("','", self::DAILY_SUB_SLUGS) . "')")->first()
            : null;

        $categoryId = $parent?->id;
        $subId      = $sub?->id;
        $difficulty = in_array($opts['difficulty'] ?? 'medium', ['easy', 'medium', 'hard'], true) ? $opts['difficulty'] : 'medium';
        $dateLong   = $date->format('d F Y');

        return DB::transaction(function () use ($date, $parsed, $opts, $categoryId, $subId, $difficulty, $dateLong) {
            $quiz = new Quiz();
            $quiz->title           = "Daily Current Affairs Quiz – {$dateLong}";
            $quiz->slug            = $this->slugForDate($date);
            $quiz->description     = "Attempt the daily current affairs quiz for {$dateLong} covering national and international news, awards, sports, economy, defence and more.";
            $quiz->category_id     = $categoryId;
            $quiz->sub_category_id = $subId;
            $quiz->quiz_type       = 'free';
            $quiz->price           = 0;
            $quiz->difficulty      = $difficulty;
            $quiz->total_questions = count($parsed);
            $quiz->question_limit  = 0;
            $quiz->time_limit      = (int) ($opts['time_limit'] ?? 10);
            $quiz->pass_percentage = (int) ($opts['pass_percentage'] ?? 40);
            $quiz->marks_per_correct = 1;
            $quiz->negative_marking  = 0;
            $quiz->randomize_questions = true;
            $quiz->randomize_options   = true;
            $quiz->show_result         = true;
            $quiz->show_correct_answers = true;
            $quiz->show_explanation     = true;
            $quiz->status = ($opts['publish'] ?? true) ? Quiz::STATUS_PUBLISHED : 'draft';

            // Date-based SEO so every daily page is optimised automatically.
            $quiz->meta_title       = "Daily Current Affairs Quiz – {$dateLong} | GK Questions";
            $quiz->meta_description = "Practice the {$dateLong} current affairs quiz with " . count($parsed) . " questions on national & international news, awards, sports, economy and defence. Free, with answers and explanations.";
            $quiz->seo_h1           = "Daily Current Affairs Quiz – {$dateLong}";
            $quiz->seo_intro        = "Test yourself on the most important current affairs of {$dateLong}. Each question comes with the correct answer and a short explanation — ideal for SSC, Banking, Railway, UPSC and State PSC preparation.";
            $quiz->primary_keyword  = "current affairs {$date->format('d F Y')}";
            $quiz->secondary_keywords = "daily current affairs quiz, current affairs " . $date->format('d F Y') . ", today current affairs, gk questions " . $date->format('F Y');
            $quiz->search_intent    = 'Current Affairs';
            $quiz->seo_priority     = 'P0';
            $quiz->seo_updated_at   = now();
            $quiz->save();

            $order = 0;
            foreach ($parsed as $pq) {
                $bq = new BankQuestion();
                $bq->category_id     = $categoryId;
                $bq->sub_category_id = $subId;
                $bq->question_type   = BankQuestion::TYPE_MCQ_SINGLE;
                $bq->difficulty      = $difficulty;
                $bq->question_text   = $pq['text'];
                $bq->explanation     = $pq['explanation'] ?: null;
                $bq->default_marks   = 1;
                $bq->status          = 1;
                $bq->save();

                $sort = 0;
                $correctOptionId = null;
                // Preserve A,B,C,D ordering.
                ksort($pq['options']);
                foreach ($pq['options'] as $letter => $o) {
                    $opt = new BankOption();
                    $opt->bank_question_id = $bq->id;
                    $opt->option_text = $o['text'];
                    $opt->is_correct  = ($letter === $pq['correct']);
                    $opt->sort_order  = $sort++;
                    $opt->save();
                    if ($letter === $pq['correct']) {
                        $correctOptionId = $opt->id;
                    }
                }
                $bq->correct_option_id = $correctOptionId;
                $bq->save();

                DB::table('quiz_bank_question')->insert([
                    'quiz_id'          => $quiz->id,
                    'bank_question_id' => $bq->id,
                    'question_order'   => ++$order,
                    'marks'            => 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            return $quiz;
        });
    }
}
