<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedQuestion;
use App\Models\AiGenerationSetting;
use App\Models\AiQuestionGeneration;
use App\Models\Category;
use App\Models\Quiz;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\QuestionApprovalService;
use App\Services\Ai\QuestionGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiQuestionGeneratorController extends Controller {
    public function __construct(
        private QuestionGeneratorService $generator,
        private QuestionApprovalService $approver,
    ) {}

    // ------------------------------------------------------------- generate

    public function create() {
        $pageTitle  = 'AI Question Generator';
        $settings   = AiGenerationSetting::config();
        $categories = Category::onlyParent()->active()->orderBy('name')->get();
        $quizzes    = Quiz::orderBy('title')->get(['id', 'title']);

        return view('admin.ai_generator.generate', compact('pageTitle', 'settings', 'categories', 'quizzes'));
    }

    public function generate(Request $request) {
        $settings = AiGenerationSetting::config();

        $validated = $request->validate([
            'category_id'             => 'required|integer|exists:categories,id',
            'sub_category_id'         => 'nullable|integer|exists:categories,id',
            'quiz_id'                 => 'nullable|integer|exists:quizzes,id',
            'topic'                   => 'nullable|string|max:255',
            'difficulty'              => 'required|in:easy,medium,hard,expert',
            'question_type'           => 'required|in:mcq,true_false',
            'language'                => 'required|in:english,hindi',
            'quantity'                => 'required|integer|min:1|max:' . $settings->max_quantity,
            'additional_instructions' => 'nullable|string|max:5000',
        ]);

        // The category must be top-level and the sub-category must belong to it;
        // exists: alone would let an arbitrary category id through (§18).
        $category = Category::find($validated['category_id']);
        if ($category->parent_id !== null) {
            $notify[] = ['error', 'Please choose a top-level category.'];
            return back()->withInput()->withNotify($notify);
        }

        if (!empty($validated['sub_category_id'])) {
            $sub = Category::find($validated['sub_category_id']);
            if ($sub->parent_id !== $category->id) {
                $notify[] = ['error', 'The selected sub-category does not belong to that category.'];
                return back()->withInput()->withNotify($notify);
            }
        }

        try {
            $generation = $this->generator->run($validated, auth('admin')->id());
        } catch (AiProviderException $e) {
            // Failed generations are still recorded in history with the reason.
            $notify[] = ['error', $e->getMessage()];
            return back()->withInput()->withNotify($notify);
        } catch (\Throwable $e) {
            $notify[] = ['error', 'Generation failed: ' . $e->getMessage()];
            return back()->withInput()->withNotify($notify);
        }

        $notify[] = ['success', "{$generation->generated_count} question(s) generated. Review them below before importing."];
        return to_route('admin.ai-generator.preview', $generation->id)->withNotify($notify);
    }

    /** AJAX: sub-categories for the chosen parent. */
    public function subCategories(Request $request) {
        $request->validate(['category_id' => 'required|integer|exists:categories,id']);

        $subCategories = Category::where('parent_id', $request->category_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $subCategories]);
    }

    // -------------------------------------------------------------- preview

    public function preview($id) {
        $generation = AiQuestionGeneration::with(['category', 'subCategory', 'quiz', 'creator'])
            ->findOrFail($id);

        $pageTitle = 'Review Generation #' . $generation->id;

        $filter  = request('status');
        $allowed = [
            AiGeneratedQuestion::STATUS_PENDING_REVIEW,
            AiGeneratedQuestion::STATUS_APPROVED,
            AiGeneratedQuestion::STATUS_REJECTED,
            AiGeneratedQuestion::STATUS_DUPLICATE,
            AiGeneratedQuestion::STATUS_PUBLISHED,
        ];

        $questions = $generation->questions()
            ->with(['duplicateOf', 'bankQuestion', 'reviewer'])
            ->when(in_array($filter, $allowed, true), fn($q) => $q->where('status', $filter))
            ->get();

        $quizzes = Quiz::orderBy('title')->get(['id', 'title']);

        return view('admin.ai_generator.preview', compact('pageTitle', 'generation', 'questions', 'filter', 'quizzes'));
    }

    // --------------------------------------------------------------- review

    /** Bulk approve / reject / delete / regenerate from the review screen. */
    public function bulkAction(Request $request, $id) {
        $request->validate([
            'action'      => 'required|in:approve,reject,delete,regenerate,keep_anyway',
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => 'integer',
        ]);

        $generation = AiQuestionGeneration::findOrFail($id);
        $ids        = $request->question_ids;
        $adminId    = auth('admin')->id();

        try {
            switch ($request->action) {
                case 'approve':
                    $result = $this->approver->approve($generation, $ids, $adminId);
                    $message = "{$result['imported']} question(s) imported into the Question Bank.";
                    if ($result['skipped']) {
                        $message .= " {$result['skipped']} skipped.";
                    }
                    $notify[] = [$result['imported'] ? 'success' : 'warning', $message];
                    foreach ($result['errors'] as $error) {
                        $notify[] = ['warning', $error];
                    }
                    break;

                case 'reject':
                    $affected = $this->updateOwned($generation, $ids, [
                        'status'      => AiGeneratedQuestion::STATUS_REJECTED,
                        'reviewed_by' => $adminId,
                        'reviewed_at' => now(),
                    ]);
                    $notify[] = ['success', "{$affected} question(s) rejected."];
                    break;

                case 'keep_anyway':
                    $affected = $this->updateOwned($generation, $ids, [
                        'duplicate_overridden' => true,
                        'status'               => AiGeneratedQuestion::STATUS_PENDING_REVIEW,
                        'reviewed_by'          => $adminId,
                        'reviewed_at'          => now(),
                    ]);
                    $notify[] = ['success', "{$affected} duplicate(s) cleared for import."];
                    break;

                case 'delete':
                    // Published questions keep their bank rows; only the AI
                    // review record is removed.
                    $affected = $generation->questions()->whereIn('id', $ids)->delete();
                    $notify[] = ['success', "{$affected} generated question(s) deleted."];
                    break;

                case 'regenerate':
                    [$ok, $failed] = $this->regenerateMany($generation, $ids);
                    $notify[] = [$ok ? 'success' : 'error', "{$ok} question(s) regenerated." . ($failed ? " {$failed} failed." : '')];
                    break;
            }
        } catch (\Throwable $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }

        $generation->syncCounts();
        return back()->withNotify($notify);
    }

    /** Scopes the update to rows actually belonging to this generation (§18). */
    private function updateOwned(AiQuestionGeneration $generation, array $ids, array $attributes): int {
        return $generation->questions()
            ->whereIn('id', $ids)
            ->where('status', '!=', AiGeneratedQuestion::STATUS_PUBLISHED)
            ->update($attributes);
    }

    private function regenerateMany(AiQuestionGeneration $generation, array $ids): array {
        $ok = 0;
        $failed = 0;

        foreach ($generation->questions()->whereIn('id', $ids)->get() as $question) {
            try {
                $this->generator->regenerateOne($question);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return [$ok, $failed];
    }

    /** Regenerates one question, keeping the batch's settings. */
    public function regenerate($id, $questionId) {
        $generation = AiQuestionGeneration::findOrFail($id);
        $question   = $generation->questions()->findOrFail($questionId);

        try {
            $this->generator->regenerateOne($question);
        } catch (\Throwable $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Question regenerated. Review it before importing.'];
        return back()->withNotify($notify);
    }

    /** Edits a generated question, then re-validates and re-checks duplicates. */
    public function updateQuestion(Request $request, $id, $questionId) {
        $generation = AiQuestionGeneration::findOrFail($id);
        $question   = $generation->questions()->findOrFail($questionId);

        if ($question->isPublished()) {
            $notify[] = ['error', 'This question is already in the Question Bank and can no longer be edited here.'];
            return back()->withNotify($notify);
        }

        $request->validate([
            'question'       => 'required|string|min:10',
            'options'        => 'required|array|min:2|max:4',
            'options.*'      => 'nullable|string',
            'correct_answer' => 'required|string|max:2',
            'explanation'    => 'required|string',
            'difficulty'     => 'required|in:easy,medium,hard,expert',
        ]);

        $options = [];
        foreach ($request->options as $letter => $text) {
            $letter = strtoupper(trim((string) $letter));
            $text   = trim(strip_tags((string) $text));
            if ($letter !== '' && $text !== '') {
                $options[$letter] = $text;
            }
        }

        $question->fill([
            'question'       => trim(strip_tags($request->question)),
            'options'        => $options,
            'correct_answer' => strtoupper(trim($request->correct_answer)),
            'explanation'    => trim(strip_tags($request->explanation)),
            'difficulty'     => $request->difficulty,
        ]);

        // Re-validate exactly as generated output is validated.
        $errors = $this->generator->validateQuestion([
            'question'       => $question->question,
            'options'        => $question->options,
            'correct_answer' => $question->correct_answer,
            'explanation'    => $question->explanation,
            'difficulty'     => $question->difficulty,
            'question_type'  => $question->question_type,
        ]);

        $question->validation_errors = $errors ? implode(' ', $errors) : null;
        $question->status = $errors
            ? AiGeneratedQuestion::STATUS_REJECTED
            : AiGeneratedQuestion::STATUS_PENDING_REVIEW;

        $this->generator->applyDuplicateFlags($question, $generation);
        $question->reviewed_by = auth('admin')->id();
        $question->reviewed_at = now();
        $question->save();

        $generation->syncCounts();

        $notify[] = $errors
            ? ['warning', 'Saved, but the question is still invalid: ' . implode(' ', $errors)]
            : ['success', 'Question updated and re-validated.'];

        return back()->withNotify($notify);
    }

    public function deleteQuestion($id, $questionId) {
        $generation = AiQuestionGeneration::findOrFail($id);
        $generation->questions()->where('id', $questionId)->delete();
        $generation->syncCounts();

        $notify[] = ['success', 'Generated question deleted.'];
        return back()->withNotify($notify);
    }

    // ----------------------------------------------------------- add to quiz

    public function addToQuiz(Request $request, $id) {
        $request->validate(['quiz_id' => 'required|integer|exists:quizzes,id']);

        $generation = AiQuestionGeneration::findOrFail($id);
        $quiz       = Quiz::findOrFail($request->quiz_id);

        try {
            $result = $this->approver->attachToQuiz($generation, $quiz);
        } catch (\Throwable $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }

        $message = "{$result['attached']} question(s) added to \"{$quiz->title}\".";
        if ($result['skipped']) {
            $message .= " {$result['skipped']} were already in the quiz.";
        }

        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

    // -------------------------------------------------------------- history

    public function history() {
        $pageTitle = 'AI Generation History';
        $generations = AiQuestionGeneration::with(['category', 'subCategory', 'creator'])
            ->searchable(['topic'])
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->when(request('category_id'), fn($q) => $q->where('category_id', request('category_id')))
            ->latest()
            ->paginate(getPaginate());

        $categories = Category::onlyParent()->active()->orderBy('name')->get();

        return view('admin.ai_generator.history', compact('pageTitle', 'generations', 'categories'));
    }

    /** Flat list of every generated question across all generations. */
    public function generatedQuestions() {
        $pageTitle = 'AI Generated Questions';
        $questions = AiGeneratedQuestion::with(['generation.category', 'generation.subCategory', 'bankQuestion'])
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->when(request('search'), fn($q) => $q->where('question', 'like', '%' . request('search') . '%'))
            ->latest('id')
            ->paginate(getPaginate());

        return view('admin.ai_generator.questions', compact('pageTitle', 'questions'));
    }

    public function destroy($id) {
        $generation = AiQuestionGeneration::findOrFail($id);

        // Bank questions already promoted are intentionally left in place.
        $generation->questions()->delete();
        $generation->forceDelete();

        $notify[] = ['success', 'Generation deleted. Questions already imported into the Question Bank were kept.'];
        return to_route('admin.ai-generator.history')->withNotify($notify);
    }

    public function cancel($id) {
        $generation = AiQuestionGeneration::findOrFail($id);
        $generation->status = AiQuestionGeneration::STATUS_CANCELLED;
        $generation->save();

        $notify[] = ['success', 'Generation cancelled.'];
        return back()->withNotify($notify);
    }

    /** Raw provider payload, for debugging a bad generation. */
    public function rawResponse($id) {
        $generation = AiQuestionGeneration::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => json_decode($generation->raw_response ?: '{}', true),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
