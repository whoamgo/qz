<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionBankController extends Controller {
    public function index() {
        $pageTitle = "Question Bank";
        $questions = BankQuestion::with(['category', 'subCategory', 'options', 'correctOption'])
            ->searchable(['question_text', 'explanation', 'hint'])
            ->when(request('category_id'), function ($q) {
                $q->where('category_id', request('category_id'));
            })
            ->when(request('sub_category_id'), function ($q) {
                $q->where('sub_category_id', request('sub_category_id'));
            })
            ->when(request('difficulty'), function ($q) {
                $q->where('difficulty', request('difficulty'));
            })
            ->when(request('question_type'), function ($q) {
                $q->where('question_type', request('question_type'));
            })
            ->latest()
            ->paginate(getPaginate());

        $categories = Category::onlyParent()->active()->orderBy('name')->get();
        return view('admin.question_bank.index', compact('pageTitle', 'questions', 'categories'));
    }

    public function store(Request $request, $id = 0) {
        $request->validate([
            'category_id'     => 'nullable|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'question_type'   => 'required|in:mcq_single,mcq_multi,true_false',
            'difficulty'      => 'required|in:easy,medium,hard',
            'question_text'   => 'required|string',
            'explanation'     => 'nullable|string',
            'hint'            => 'nullable|string|max:255',
            'default_marks'   => 'required|numeric|min:0',
        ]);

        $resolvedId = $id ?: (int) $request->input('question_id', 0);

        DB::beginTransaction();
        try {
            if ($resolvedId) {
                $question = BankQuestion::findOrFail($resolvedId);
                $message = 'Question updated successfully';
            } else {
                $question = new BankQuestion();
                $message = 'Question created successfully';
            }

            $question->category_id = $request->category_id;
            $question->sub_category_id = $request->sub_category_id;
            $question->question_type = $request->question_type;
            $question->difficulty = $request->difficulty;
            $question->question_text = $request->question_text;
            $question->explanation = $request->explanation;
            $question->hint = $request->hint;
            $question->default_marks = $request->default_marks;
            $question->save();

            if ($request->has('options') && is_array($request->options)) {
                $question->options()->delete();
                $correctOptionId = null;

                foreach ($request->options as $index => $optionData) {
                    if (empty($optionData['text'])) {
                        continue;
                    }
                    $option = new BankOption();
                    $option->bank_question_id = $question->id;
                    $option->option_text = $optionData['text'];
                    $option->sort_order = $index;
                    $option->is_correct = isset($optionData['is_correct']) && $optionData['is_correct'] == 1;
                    $option->save();

                    if ($option->is_correct) {
                        $correctOptionId = $option->id;
                    }
                }

                if ($correctOptionId) {
                    $question->correct_option_id = $correctOptionId;
                    $question->save();
                }
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $question->load(['options', 'correctOption'])
                ]);
            }

            $notify[] = ['success', $message];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [$e->getMessage()]
                ], 422);
            }

            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function delete($id) {
        $question = BankQuestion::findOrFail($id);
        $question->delete();

        $notify[] = ['success', 'Question deleted successfully'];
        return back()->withNotify($notify);
    }

    public function status($id) {
        return BankQuestion::changeStatus($id);
    }

    public function addToQuiz(Request $request, $quizId) {
        $request->validate([
            'bank_question_id' => 'required|integer|exists:bank_questions,id',
            'marks' => 'nullable|numeric|min:0',
        ]);

        $quiz = Quiz::findOrFail($quizId);
        $question = BankQuestion::findOrFail($request->bank_question_id);

        $existingPivot = DB::table('quiz_bank_question')
            ->where('quiz_id', $quiz->id)
            ->where('bank_question_id', $question->id)
            ->first();

        if ($existingPivot) {
            $notify[] = ['error', 'Question already added to this quiz'];
            return back()->withNotify($notify);
        }

        $maxOrder = DB::table('quiz_bank_question')
            ->where('quiz_id', $quiz->id)
            ->max('question_order');

        DB::table('quiz_bank_question')->insert([
            'quiz_id'         => $quiz->id,
            'bank_question_id' => $question->id,
            'question_order'  => ($maxOrder ?? 0) + 1,
            'marks'           => $request->marks ?? $question->default_marks,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $quiz->total_questions = $quiz->questions()->count();
        $quiz->save();

        $notify[] = ['success', 'Question added to quiz successfully'];
        return back()->withNotify($notify);
    }

    public function removeFromQuiz($quizId, $questionId) {
        $quiz = Quiz::findOrFail($quizId);

        DB::table('quiz_bank_question')
            ->where('quiz_id', $quiz->id)
            ->where('bank_question_id', $questionId)
            ->delete();

        $this->reorderQuizQuestions($quiz->id);

        $quiz->total_questions = $quiz->questions()->count();
        $quiz->save();

        $notify[] = ['success', 'Question removed from quiz successfully'];
        return back()->withNotify($notify);
    }

    public function reorderQuestions(Request $request, $quizId) {
        $request->validate([
            'orders' => 'required|array',
            'orders.*' => 'integer',
        ]);

        $quiz = Quiz::findOrFail($quizId);

        foreach ($request->orders as $order => $questionId) {
            DB::table('quiz_bank_question')
                ->where('quiz_id', $quiz->id)
                ->where('bank_question_id', $questionId)
                ->update(['question_order' => $order + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Questions reordered successfully'
        ]);
    }

    private function reorderQuizQuestions($quizId) {
        $questions = DB::table('quiz_bank_question')
            ->where('quiz_id', $quizId)
            ->orderBy('question_order', 'asc')
            ->get();

        foreach ($questions as $index => $pivot) {
            DB::table('quiz_bank_question')
                ->where('id', $pivot->id)
                ->update(['question_order' => $index + 1]);
        }
    }

    public function updateQuestionMarks(Request $request, $quizId, $questionId) {
        $request->validate([
            'marks' => 'required|numeric|min:0',
        ]);

        DB::table('quiz_bank_question')
            ->where('quiz_id', $quizId)
            ->where('bank_question_id', $questionId)
            ->update(['marks' => $request->marks]);

        return response()->json([
            'success' => true,
            'message' => 'Marks updated successfully'
        ]);
    }

    public function storeOption(Request $request, $questionId, $optionId = 0) {
        $request->validate([
            'option_text' => 'required|string',
            'is_correct'  => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $question = BankQuestion::findOrFail($questionId);

        if ($optionId) {
            $option = BankOption::where('id', $optionId)->where('bank_question_id', $question->id)->firstOrFail();
            $message = 'Option updated successfully';
        } else {
            $option = new BankOption();
            $option->bank_question_id = $question->id;
            $message = 'Option added successfully';
        }

        $option->option_text = $request->option_text;
        $option->is_correct = $request->boolean('is_correct');
        $option->sort_order = $request->sort_order ?? $question->options()->count();
        $option->save();

        if ($option->is_correct) {
            BankOption::where('bank_question_id', $question->id)
                ->where('id', '!=', $option->id)
                ->update(['is_correct' => false]);

            $question->correct_option_id = $option->id;
            $question->save();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $option
        ]);
    }

    public function deleteOption($questionId, $optionId) {
        $question = BankQuestion::findOrFail($questionId);
        $option = BankOption::where('id', $optionId)->where('bank_question_id', $question->id)->firstOrFail();
        $option->delete();

        if ($question->correct_option_id == $optionId) {
            $question->correct_option_id = null;
            $question->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Option deleted successfully'
        ]);
    }

    public function getAvailableQuestions($quizId) {
        $quiz = Quiz::findOrFail($quizId);

        $attachedIds = DB::table('quiz_bank_question')
            ->where('quiz_id', $quiz->id)
            ->pluck('bank_question_id')
            ->toArray();

        $questions = BankQuestion::with(['category', 'subCategory'])
            ->whereNotIn('id', $attachedIds)
            ->when($quiz->category_id, function ($q) use ($quiz) {
                $q->where('category_id', $quiz->category_id);
            })
            ->when($quiz->sub_category_id, function ($q) use ($quiz) {
                $q->where('sub_category_id', $quiz->sub_category_id);
            })
            ->when(request('category_id'), function ($q) {
                $q->where('category_id', request('category_id'));
            })
            ->when(request('difficulty'), function ($q) {
                $q->where('difficulty', request('difficulty'));
            })
            ->searchable(['question_text'])
            ->active()
            ->latest()
            ->paginate(getPaginate());

        $categories = Category::onlyParent()->active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'questions' => $questions->items(),
                'pagination' => [
                    'total' => $questions->total(),
                    'current_page' => $questions->currentPage(),
                    'last_page' => $questions->lastPage(),
                    'per_page' => $questions->perPage(),
                ],
                'categories' => $categories,
            ]
        ]);
    }
}
