<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LevelController extends Controller {
    public function index(): View {
        $pageTitle = "Manage Levels";
        $levels = Level::orderBy('level_number')->paginate(getPaginate());

        return view('admin.gamification.levels.index', compact('pageTitle', 'levels'));
    }

    public function create(): View {
        $pageTitle = "Add Level";
        $level = null;
        return view('admin.gamification.levels.form', compact('pageTitle', 'level'));
    }

    public function store(Request $request) {
        $request->validate([
            'level_number' => 'required|integer|min:1|unique:levels,level_number',
            'name' => 'required|string|max:255',
            'required_xp' => 'required|integer|min:0',
            'description' => 'nullable|string|max:500',
            'badge_icon' => 'nullable|string|max:255',
            'badge_color' => 'nullable|string|max:20',
            'reward_xp' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        Level::create($request->all());

        $notify[] = ['success', 'Level created successfully'];
        return redirect()->route('admin.levels.index')->withNotify($notify);
    }

    public function edit($id): View {
        $pageTitle = "Edit Level";
        $level = Level::findOrFail($id);

        return view('admin.gamification.levels.form', compact('pageTitle', 'level'));
    }

    public function update(Request $request, $id) {
        $request->validate([
            'level_number' => 'required|integer|min:1|unique:levels,level_number,' . $id,
            'name' => 'required|string|max:255',
            'required_xp' => 'required|integer|min:0',
            'description' => 'nullable|string|max:500',
            'badge_icon' => 'nullable|string|max:255',
            'badge_color' => 'nullable|string|max:20',
            'reward_xp' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $level = Level::findOrFail($id);
        $level->update($request->all());

        $notify[] = ['success', 'Level updated successfully'];
        return redirect()->route('admin.levels.index')->withNotify($notify);
    }

    public function status($id) {
        $level = Level::findOrFail($id);
        $level->update(['is_active' => !$level->is_active]);

        return response()->json(['success' => true]);
    }

    public function destroy($id) {
        Level::findOrFail($id)->delete();

        $notify[] = ['success', 'Level deleted successfully'];
        return back()->withNotify($notify);
    }
}
