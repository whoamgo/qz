<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\XpRule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class XpRulesController extends Controller {
    public function index(Request $request): View {
        $pageTitle = "XP Rules Management";

        $query = XpRule::query();

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status == 'active');
        }

        if ($request->has('search') && $request->search) {
            $search = "%{$request->search}%";
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('key', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $rules = $query->orderBy('sort_order')->paginate(getPaginate());

        $categories = $this->getCategories();

        return view('admin.gamification.xp_rules.index', compact(
            'pageTitle',
            'rules',
            'categories'
        ));
    }

    public function create(): View {
        $pageTitle = "Add XP Rule";
        $categories = $this->getCategories();
        $rule = null;

        return view('admin.gamification.xp_rules.form', compact(
            'pageTitle',
            'categories',
            'rule'
        ));
    }

    public function store(Request $request, $id = null) {
        $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:100|unique:xp_rules,key' . ($id ? ',' . $id : ''),
            'description' => 'nullable|string|max:500',
            'xp_value' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'daily_limit' => 'nullable|integer|min:1',
            'weekly_limit' => 'nullable|integer|min:1',
            'cooldown_minutes' => 'required|integer|min:0',
            'category' => 'required|in:quiz,learning,streak,other',
            'sort_order' => 'required|integer',
        ]);

        if ($id) {
            $rule = XpRule::findOrFail($id);
            $rule->update($request->except('_token'));
            $message = 'XP Rule updated successfully';
        } else {
            XpRule::create($request->except('_token'));
            $message = 'XP Rule created successfully';
        }

        $notify[] = ['success', $message];
        return redirect()->route('admin.xp.rules.index')->withNotify($notify);
    }

    public function edit($id): View {
        $pageTitle = "Edit XP Rule";
        $rule = XpRule::withTrashed()->findOrFail($id);
        $categories = $this->getCategories();

        return view('admin.gamification.xp_rules.form', compact(
            'pageTitle',
            'rule',
            'categories'
        ));
    }

    public function status(Request $request, $id) {
        $rule = XpRule::findOrFail($id);
        $rule->update(['is_active' => $request->status == 'on']);

        return response()->json([
            'success' => true,
            'message' => 'Rule status updated'
        ]);
    }

    public function destroy($id) {
        $rule = XpRule::findOrFail($id);
        $rule->delete();

        $notify[] = ['success', 'XP Rule deleted successfully'];
        return back()->withNotify($notify);
    }

    public function reorder(Request $request) {
        $request->validate([
            'items' => 'required|array',
        ]);

        foreach ($request->items as $index => $ruleId) {
            XpRule::where('id', $ruleId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    protected function getCategories() {
        return [
            'quiz' => 'Quiz',
            'learning' => 'Learning',
            'streak' => 'Streak',
            'other' => 'Other',
        ];
    }
}
