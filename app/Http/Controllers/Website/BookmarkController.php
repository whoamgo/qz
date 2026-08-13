<?php

namespace App\Http\Controllers\Website;

use App\Models\Bookmark;
use Illuminate\Http\Request;

class BookmarkController extends BaseWebsiteController {
    /** AJAX toggle for either a quiz or a bank question. */
    public function toggle(Request $request) {
        $request->validate([
            'type' => 'required|in:quiz,question',
            'id'   => 'required|integer',
        ]);

        $column = $request->type === 'quiz' ? 'quiz_id' : 'bank_question_id';
        $table  = $request->type === 'quiz' ? 'quizzes' : 'bank_questions';

        // Validate the target exists before writing, so a bad id cannot create
        // an orphan row that would then fail the foreign key.
        if (!\DB::table($table)->where('id', $request->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $existing = Bookmark::where('user_id', auth()->id())
            ->where($column, $request->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'bookmarked' => false, 'message' => 'Bookmark removed.']);
        }

        Bookmark::create([
            'user_id' => auth()->id(),
            $column   => $request->id,
        ]);

        return response()->json(['success' => true, 'bookmarked' => true, 'message' => 'Saved to your bookmarks.']);
    }
}
