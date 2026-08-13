<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgeController extends Controller {
    public function index(Request $request): View {
        $pageTitle = "Manage Badges";

        $query = Badge::query();

        if ($request->has('status')) {
            $query->where('is_active', $request->status == 'active');
        }

        if ($request->has('search') && $request->search) {
            $search = "%{$request->search}%";
            $query->where('name', 'like', $search)
                  ->orWhere('description', 'like', $search);
        }

        $badges = $query->orderBy('sort_order')->paginate(getPaginate());
        $conditionTypes = $this->getConditionTypes();

        return view('admin.gamification.badges.index', compact('pageTitle', 'badges', 'conditionTypes'));
    }

    public function create(): View {
        $pageTitle = "Add Badge";
        $badge = null;
        $conditionTypes = $this->getConditionTypes();

        return view('admin.gamification.badges.form', compact('pageTitle', 'badge', 'conditionTypes'));
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:badges,slug',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'condition_type' => 'required|in:' . implode(',', array_keys($this->getConditionTypes())),
            'condition_data' => 'required|json',
            'reward_xp' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        Badge::create([
            ...$request->except('condition_data'),
            'condition_data' => json_decode($request->condition_data, true),
        ]);

        $notify[] = ['success', 'Badge created successfully'];
        return redirect()->route('admin.badges.index')->withNotify($notify);
    }

    public function edit($id): View {
        $pageTitle = "Edit Badge";
        $badge = Badge::findOrFail($id);
        $conditionTypes = $this->getConditionTypes();

        return view('admin.gamification.badges.form', compact('pageTitle', 'badge', 'conditionTypes'));
    }

    public function update(Request $request, $id) {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:badges,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'condition_type' => 'required|in:' . implode(',', array_keys($this->getConditionTypes())),
            'condition_data' => 'required|json',
            'reward_xp' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $badge = Badge::findOrFail($id);
        $badge->update([
            ...$request->except('condition_data'),
            'condition_data' => json_decode($request->condition_data, true),
        ]);

        $notify[] = ['success', 'Badge updated successfully'];
        return redirect()->route('admin.badges.index')->withNotify($notify);
    }

    public function status($id) {
        $badge = Badge::findOrFail($id);
        $badge->update(['is_active' => !$badge->is_active]);

        return response()->json(['success' => true]);
    }

    public function destroy($id) {
        Badge::findOrFail($id)->delete();

        $notify[] = ['success', 'Badge deleted successfully'];
        return back()->withNotify($notify);
    }

    protected function getConditionTypes() {
        return [
            'quiz_count' => 'Quiz Count',
            'question_count' => 'Question Count',
            'correct_answer_count' => 'Correct Answers',
            'perfect_score_count' => 'Perfect Scores',
            'streak_days' => 'Streak Days',
            'total_xp' => 'Total XP',
            'category_quiz_count' => 'Category Quiz Count',
            'exam_quiz_count' => 'Exam Quiz Count',
            'leaderboard_rank' => 'Leaderboard Rank',
        ];
    }
}
