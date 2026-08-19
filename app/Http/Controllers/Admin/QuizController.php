<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizBankQuestion;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller {
    public function index() {
        $pageTitle = "All Quizzes";
        $quizzes = Quiz::with(['category', 'subCategory'])
            ->searchable(['title', 'slug', 'description'])
            ->when(request('category_id'), function ($q) {
                $q->where('category_id', request('category_id'));
            })
            ->when(request('sub_category_id'), function ($q) {
                $q->where('sub_category_id', request('sub_category_id'));
            })
            ->when(request('status'), function ($q) {
                $q->where('status', request('status'));
            })
            ->when(request('difficulty'), function ($q) {
                $q->where('difficulty', request('difficulty'));
            })
            ->when(request('quiz_type'), function ($q) {
                $q->where('quiz_type', request('quiz_type'));
            })
            ->latest()
            ->paginate(getPaginate());

        $categories = Category::onlyParent()->active()->orderBy('name')->get();
        $subCategories = Category::onlyChild()->active()->orderBy('name')->get();

        return view('admin.quiz.index', compact('pageTitle', 'quizzes', 'categories', 'subCategories'));
    }

    public function create($id = 0) {
        $pageTitle = "Add Quiz";
        $quiz = null;

        if ($id) {
            $pageTitle = "Edit Quiz";
            $quiz = Quiz::findOrFail($id);
        }

        $categories = Category::onlyParent()->active()->orderBy('name')->get();
        $subCategories = collect();
        if ($quiz && $quiz->category_id) {
            $subCategories = Category::where('parent_id', $quiz->category_id)->active()->orderBy('name')->get();
        }

        return view('admin.quiz.create', compact('pageTitle', 'quiz', 'categories', 'subCategories'));
    }

    /**
     * Validates the category / sub-category pairing before the main rules run,
     * so the message points at the field the admin actually needs to fix.
     */
    private function subCategoryRule(Request $request): void {
        $categoryId = (int) $request->input('category_id');
        if (!$categoryId) { return; }

        $hasChildren = \App\Models\Category::where('parent_id', $categoryId)
            ->where('status', 1)->exists();

        $chosen = $request->input('sub_category_id');

        if ($hasChildren && !$chosen) {
            abort(redirect()->back()->withInput()->withErrors([
                'sub_category_id' => 'This category has sub-categories, so please choose one.',
            ]));
        }

        if ($chosen) {
            $belongs = \App\Models\Category::where('id', $chosen)
                ->where('parent_id', $categoryId)->exists();
            if (!$belongs) {
                abort(redirect()->back()->withInput()->withErrors([
                    'sub_category_id' => 'The chosen sub-category does not belong to that category.',
                ]));
            }
        }
    }

    public function store(Request $request, $id = 0) {
        $imgValidation = $id ? 'nullable' : 'nullable';
        // A sub-category is mandatory only when the chosen category actually
        // has sub-categories. Requiring it unconditionally would block every
        // flat category; leaving it optional lets quizzes drift out of the
        // Category -> Sub-category -> Quiz structure.
        $this->subCategoryRule($request);

        $request->validate([
            'title'               => 'required|string|max:255',
            'slug'                => 'required|string|max:255|unique:quizzes,slug,' . $id,
            'description'         => 'nullable|string',
            'category_id'         => 'required|integer|exists:categories,id',
            'sub_category_id'     => 'nullable|integer|exists:categories,id',
            // Conditionally required below: a category that HAS sub-categories
            // must have one chosen; a flat category must not.
            'quiz_type'           => 'required|in:free,paid,subscription',
            'price'               => 'required_if:quiz_type,paid|numeric|min:0',
            'difficulty'          => 'required|in:easy,medium,hard',
            'total_questions'     => 'required|integer|min:0',
            'question_limit'      => 'nullable|integer|min:0',
            'time_limit'          => 'required|integer|min:0',
            'pass_percentage'     => 'required|integer|min:0|max:100',
            'marks_per_correct'   => 'required|numeric|min:0',
            'negative_marking'    => 'required|numeric|min:0',
            'randomize_questions' => 'nullable|boolean',
            'randomize_options'     => 'nullable|boolean',
            'show_result'         => 'nullable|boolean',
            'show_correct_answers' => 'nullable|boolean',
            'show_explanation'   => 'nullable|boolean',
            'is_popular'          => 'nullable|boolean',
            'status'              => 'required|in:draft,published,archived',
            'image'               => [$imgValidation, 'image', new FileTypeValidate(['jpeg', 'jpg', 'png', 'webp'])],
        ]);

        DB::beginTransaction();
        try {
            if ($id) {
                $quiz = Quiz::findOrFail($id);
                $message = 'Quiz updated successfully';
            } else {
                $quiz = new Quiz();
                $message = 'Quiz created successfully';
            }

            if ($request->hasFile('image')) {
                try {
                    $old = $quiz->image;
                    $quiz->image = fileUploader($request->image, getFilePath('exam'), getFileSize('exam'), $old);
                } catch (\Exception $exp) {
                    $notify[] = ['error', 'Couldn\'t upload your image'];
                    return back()->withNotify($notify);
                }
            }

            $quiz->title = $request->title;
            $quiz->slug = slug($request->slug);
            $quiz->description = $request->description;
            $quiz->category_id = $request->category_id;
            $quiz->sub_category_id = $request->sub_category_id;
            $quiz->quiz_type = $request->quiz_type;
            $quiz->price = $request->quiz_type == 'paid' ? $request->price : 0;
            $quiz->difficulty = $request->difficulty;
            $quiz->total_questions = $request->total_questions;
            // 0 = serve every attached question; any higher value is clamped
            // to the bank size at attempt time, never here.
            $quiz->question_limit  = (int) $request->input('question_limit', 0);
            $quiz->time_limit = $request->time_limit;
            $quiz->pass_percentage = $request->pass_percentage;
            $quiz->marks_per_correct = $request->marks_per_correct;
            $quiz->negative_marking = $request->negative_marking;
            $quiz->randomize_questions = $request->boolean('randomize_questions');
            $quiz->randomize_options = $request->boolean('randomize_options');
            $quiz->show_result = $request->boolean('show_result');
            $quiz->show_correct_answers = $request->boolean('show_correct_answers');
            $quiz->show_explanation = $request->boolean('show_explanation');
            $quiz->is_popular = $request->boolean('is_popular');
            $quiz->status = $request->status;

            $quiz->save();
            DB::commit();

            // The home page caches its quiz listings; drop them so an admin's
            // Most Popular / publish change shows up without waiting for the TTL.
            $this->clearHomeQuizCache();

            $notify[] = ['success', $message];
            return to_route('admin.quiz.show', $quiz->id)->withNotify($notify);
        } catch (\Exception $e) {
            DB::rollBack();
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function show($id) {
        $quiz = Quiz::with(['category', 'subCategory', 'questions.options', 'questions.correctOption'])
            ->findOrFail($id);
        $pageTitle = "Quiz: " . $quiz->title;

        $questions = $quiz->questions;
        $totalMarks = $quiz->questions->sum(function ($q) use ($quiz) {
            return $q->pivot->marks ?? $quiz->marks_per_correct;
        });

        return view('admin.quiz.show', compact('pageTitle', 'quiz', 'questions', 'totalMarks'));
    }

    public function getSubCategories(Request $request) {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $subCategories = Category::where('parent_id', $request->category_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $subCategories
        ]);
    }

    public function changeQuizStatus($id) {
        $quiz = Quiz::findOrFail($id);

        if ($quiz->status == Quiz::STATUS_PUBLISHED) {
            $quiz->status = Quiz::STATUS_DRAFT;
        } else {
            $quiz->status = Quiz::STATUS_PUBLISHED;
        }

        $quiz->save();
        $this->clearHomeQuizCache();

        $notify[] = ['success', 'Quiz status changed successfully'];
        return back()->withNotify($notify);
    }

    public function delete($id) {
        $quiz = Quiz::findOrFail($id);
        $quiz->delete();
        $this->clearHomeQuizCache();

        $notify[] = ['success', 'Quiz deleted successfully'];
        return back()->withNotify($notify);
    }

    public function restore($id) {
        $quiz = Quiz::onlyTrashed()->findOrFail($id);
        $quiz->restore();
        $this->clearHomeQuizCache();

        $notify[] = ['success', 'Quiz restored successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Forget the home-page quiz caches so an admin change (Most Popular flag,
     * publish/unpublish, delete/restore) is reflected on the next home render
     * instead of after the cache TTL expires.
     */
    private function clearHomeQuizCache(): void {
        foreach (['website.home.featured', 'website.home.popular', 'website.home.latest'] as $key) {
            Cache::forget($key);
        }
    }

    public function preview($id) {
        $quiz = Quiz::with(['questions.options', 'questions.correctOption'])
            ->findOrFail($id);

        if ($quiz->randomize_questions) {
            $quiz->setRelation('questions', $quiz->questions->shuffle());
        }

        $pageTitle = "Preview: " . $quiz->title;
        return view('admin.quiz.preview', compact('pageTitle', 'quiz'));
    }
}
