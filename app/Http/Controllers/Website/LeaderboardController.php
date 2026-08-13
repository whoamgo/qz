<?php

namespace App\Http\Controllers\Website;

use App\Models\Category;
use App\Models\QuizAttempt;
use App\Models\UserXp;
use Illuminate\Http\Request;

class LeaderboardController extends BaseWebsiteController {
    const PERIODS = ['daily', 'weekly', 'monthly', 'all_time'];

    public function index(Request $request) {
        $period = in_array($request->period, self::PERIODS, true) ? $request->period : 'all_time';
        $categorySlug = $request->category;
        $category = $categorySlug ? $this->categoryBySlug($categorySlug) : null;

        $leaders = $this->leaders($period, $category?->id);

        // Rank of the signed-in user within the same board.
        $myRank = null;
        $myRow  = null;
        if (auth()->check()) {
            $index = $leaders->search(fn($row) => (int) $row->user_id === (int) auth()->id());
            if ($index !== false) {
                $myRank = $index + 1;
                $myRow  = $leaders[$index];
            } else {
                $myRow = $this->leaders($period, $category?->id, null)
                    ->firstWhere('user_id', auth()->id());
            }
        }

        $categories = $this->navCategories();

        $seo = $this->seo([
            'title'       => 'Leaderboard — Top Quiz Performers by XP',
            'description' => 'See the highest-scoring quiz takers by XP across daily, weekly, monthly and all-time boards, filtered by category or exam.',
            'canonical'   => route('website.leaderboard'),
            'schema'      => [$this->breadcrumbSchema([
                'Home'        => route('home'),
                'Leaderboard' => route('website.leaderboard'),
            ])],
        ]);

        return view('website.leaderboard.index', compact('seo', 'leaders', 'period', 'category', 'categories', 'myRank', 'myRow'));
    }

    /**
     * All-time reads the denormalised user_xp totals. Period and category
     * boards aggregate quiz_attempts, since XP totals are not time-sliced.
     */
    private function leaders(string $period, ?int $categoryId, ?int $limit = 50) {
        if ($period === 'all_time' && !$categoryId) {
            return UserXp::with('user:id,username,firstname,lastname,image')
                ->orderByDesc('total_xp')
                ->when($limit, fn($q) => $q->limit($limit))
                ->get()
                ->map(fn($row) => (object) [
                    'user_id'   => $row->user_id,
                    'user'      => $row->user,
                    'xp'        => (int) $row->total_xp,
                    'level'     => (int) $row->current_level,
                    'attempts'  => null,
                ]);
        }

        $query = QuizAttempt::query()
            ->where('quiz_attempts.status', QuizAttempt::STATUS_COMPLETED)
            ->join('users', 'users.id', '=', 'quiz_attempts.user_id')
            ->leftJoin('user_xp', 'user_xp.user_id', '=', 'quiz_attempts.user_id')
            ->groupBy('quiz_attempts.user_id', 'users.id', 'users.username', 'users.firstname', 'users.lastname', 'users.image', 'user_xp.current_level')
            ->selectRaw('quiz_attempts.user_id,
                         users.username, users.firstname, users.lastname, users.image,
                         COALESCE(user_xp.current_level, 1) as level,
                         SUM(quiz_attempts.xp_awarded) as xp,
                         COUNT(*) as attempts')
            ->orderByDesc('xp');

        match ($period) {
            'daily'   => $query->whereDate('quiz_attempts.submitted_at', now()->toDateString()),
            'weekly'  => $query->where('quiz_attempts.submitted_at', '>=', now()->startOfWeek()),
            'monthly' => $query->where('quiz_attempts.submitted_at', '>=', now()->startOfMonth()),
            default   => null,
        };

        if ($categoryId) {
            $query->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
                  ->where('quizzes.category_id', $categoryId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn($row) => (object) [
            'user_id'  => $row->user_id,
            'user'     => (object) [
                'id'        => $row->user_id,
                'username'  => $row->username,
                'firstname' => $row->firstname,
                'lastname'  => $row->lastname,
                'image'     => $row->image,
            ],
            'xp'       => (int) $row->xp,
            'level'    => (int) $row->level,
            'attempts' => (int) $row->attempts,
        ]);
    }
}
