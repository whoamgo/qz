<?php

namespace App\Http\Controllers\Website;

use App\Models\Badge;
use App\Models\Level;
use App\Models\QuizAttempt;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends BaseWebsiteController {
    /** Profile pages are private; none of them are indexable. */
    private function privateSeo(string $title): array {
        return $this->seo(['title' => $title, 'robots' => 'noindex, nofollow']);
    }

    public function index() {
        $user  = auth()->user();
        $stats = $this->stats($user);

        $recent = QuizAttempt::where('user_id', $user->id)
            ->completed()
            ->with('quiz.category')
            ->latest('submitted_at')
            ->limit(5)
            ->get();

        $inProgress = QuizAttempt::where('user_id', $user->id)
            ->inProgress()
            ->with('quiz')
            ->latest('id')
            ->limit(3)
            ->get();

        $badges = $user->badges()->orderByDesc('user_badges.created_at')->limit(6)->get();

        return view('website.profile.index', [
            'seo' => $this->privateSeo('My Profile'),
            'user' => $user, 'stats' => $stats, 'recent' => $recent,
            'inProgress' => $inProgress, 'badges' => $badges,
        ]);
    }

    public function quizzes(Request $request) {
        $user = auth()->user();

        $attempts = QuizAttempt::where('user_id', $user->id)
            ->with('quiz.category')
            ->when($request->status === 'passed', fn($q) => $q->completed()->where('passed', true))
            ->when($request->status === 'failed', fn($q) => $q->completed()->where('passed', false))
            ->when($request->status === 'in_progress', fn($q) => $q->inProgress())
            ->when(!$request->status, fn($q) => $q->latest('id'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('website.profile.quizzes', [
            'seo' => $this->privateSeo('My Quizzes'),
            'user' => $user, 'attempts' => $attempts, 'stats' => $this->stats($user),
        ]);
    }

    public function progress() {
        $user = auth()->user();

        // Accuracy per category, driven entirely by completed attempts.
        $byCategory = QuizAttempt::where('quiz_attempts.user_id', $user->id)
            ->where('quiz_attempts.status', QuizAttempt::STATUS_COMPLETED)
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('categories', 'categories.id', '=', 'quizzes.category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->selectRaw('categories.name, categories.slug,
                         COUNT(*) as attempts,
                         SUM(quiz_attempts.correct_count) as correct,
                         SUM(quiz_attempts.correct_count + quiz_attempts.wrong_count) as answered,
                         AVG(quiz_attempts.percentage) as avg_score')
            ->orderByDesc('attempts')
            ->get();

        // Last 30 days of activity for the trend chart.
        $timeline = QuizAttempt::where('user_id', $user->id)
            ->completed()
            ->where('submitted_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as attempts, AVG(percentage) as avg_score, SUM(xp_awarded) as xp')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return view('website.profile.progress', [
            'seo' => $this->privateSeo('My Progress'),
            'user' => $user, 'stats' => $this->stats($user),
            'byCategory' => $byCategory, 'timeline' => $timeline,
        ]);
    }

    public function xp() {
        $user = auth()->user();
        $xp   = $user->xpProfile;

        $transactions = $user->xpTransactions()->latest('id')->paginate(20);

        $currentLevel = $xp ? Level::where('level_number', $xp->current_level)->first() : null;
        $nextLevel    = $xp ? Level::where('required_xp', '>', $xp->total_xp)->orderBy('required_xp')->first() : null;

        return view('website.profile.xp', [
            'seo' => $this->privateSeo('XP History'),
            'user' => $user, 'xp' => $xp, 'transactions' => $transactions,
            'currentLevel' => $currentLevel, 'nextLevel' => $nextLevel,
            'stats' => $this->stats($user),
        ]);
    }

    public function badges() {
        $user = auth()->user();

        $earned    = $user->badges()->get()->keyBy('id');
        $allBadges = Badge::where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get();

        return view('website.profile.badges', [
            'seo' => $this->privateSeo('My Badges'),
            'user' => $user, 'earned' => $earned, 'allBadges' => $allBadges,
            'stats' => $this->stats($user),
        ]);
    }

    public function streak() {
        $user   = auth()->user();
        $streak = $user->streak;

        // Day-by-day activity for the last 12 weeks, for the heatmap.
        $activity = QuizAttempt::where('user_id', $user->id)
            ->completed()
            ->where('submitted_at', '>=', now()->subWeeks(12))
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as attempts')
            ->groupBy('day')
            ->pluck('attempts', 'day');

        return view('website.profile.streak', [
            'seo' => $this->privateSeo('My Streak'),
            'user' => $user, 'streak' => $streak, 'activity' => $activity,
            'stats' => $this->stats($user),
        ]);
    }

    public function bookmarks() {
        $user = auth()->user();

        $quizBookmarks = $user->bookmarks()
            ->quizzes()
            ->with('quiz.category')
            ->latest('id')
            ->paginate(12, ['*'], 'quizzes');

        $questionBookmarks = $user->bookmarks()
            ->questions()
            ->with('question.options', 'question.category')
            ->latest('id')
            ->paginate(12, ['*'], 'questions');

        return view('website.profile.bookmarks', [
            'seo' => $this->privateSeo('My Bookmarks'),
            'user' => $user, 'quizBookmarks' => $quizBookmarks,
            'questionBookmarks' => $questionBookmarks, 'stats' => $this->stats($user),
        ]);
    }

    public function settings() {
        return view('website.profile.settings', [
            'seo' => $this->privateSeo('Account Settings'),
            'user' => auth()->user(), 'stats' => $this->stats(auth()->user()),
        ]);
    }

    public function settingsUpdate(Request $request) {
        $user = auth()->user();

        $request->validate([
            'firstname' => 'required|string|max:40',
            'lastname'  => 'required|string|max:40',
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:80',
            'state'     => 'nullable|string|max:80',
            'zip'       => 'nullable|string|max:20',
            'image'     => ['nullable', 'image', 'max:2048', new FileTypeValidate(['jpg', 'jpeg', 'png', 'webp'])],
        ], [
            'image.max' => 'The profile photo may not be larger than 2 MB.',
        ]);

        // Replaces the old file rather than accumulating orphans; the same
        // path/size the rest of the app already uses for user avatars.
        if ($request->hasFile('image')) {
            try {
                $user->image = fileUploader(
                    $request->file('image'),
                    getFilePath('userProfile'),
                    getFileSize('userProfile'),
                    $user->image
                );
            } catch (\Throwable $e) {
                // The real reason goes to the log, not to the visitor: a storage
                // fault must not be reported as a problem with their file, and
                // exception text can leak server paths.
                Log::error('Profile photo upload failed: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'path'    => getFilePath('userProfile'),
                ]);

                $message = strtolower($e->getMessage());
                $isStorageFault = str_contains($message, 'writable')
                    || str_contains($message, 'permission')
                    || str_contains($message, 'directory');

                return back()->with('error', $isStorageFault
                    ? 'Your photo could not be saved due to a server storage problem. Your image is fine — please contact support.'
                    : 'That image could not be processed. Please try a different file.');
            }
        }

        // Only profile fields are writable here. Email, mobile, username,
        // status and verification flags are deliberately excluded.
        $user->firstname = $request->firstname;
        $user->lastname  = $request->lastname;

        $address = $user->address;
        $address = is_array($address) ? $address : (array) $address;
        $address['address'] = $request->address;
        $address['city']    = $request->city;
        $address['state']   = $request->state;
        $address['zip']     = $request->zip;
        $user->address = $address;

        $user->save();

        return back()->with('success', 'Your profile has been updated.');
    }

    /** Aggregate counters shown in the profile sidebar on every tab. */
    private function stats($user): array {
        $base = QuizAttempt::where('user_id', $user->id)->completed();

        $row = (clone $base)->selectRaw('
            COUNT(*) as attempts,
            COALESCE(SUM(correct_count), 0) as correct,
            COALESCE(SUM(correct_count + wrong_count), 0) as answered,
            COALESCE(SUM(xp_awarded), 0) as xp_from_quizzes,
            COALESCE(AVG(percentage), 0) as avg_score,
            COALESCE(SUM(time_taken), 0) as total_time
        ')->first();

        $xp = $user->xpProfile;

        return [
            'attempts'   => (int) $row->attempts,
            'correct'    => (int) $row->correct,
            'answered'   => (int) $row->answered,
            'accuracy'   => $row->answered > 0 ? round(($row->correct / $row->answered) * 100, 1) : 0,
            'avg_score'  => round((float) $row->avg_score, 1),
            'total_time' => (int) $row->total_time,
            'total_xp'   => (int) ($xp->total_xp ?? 0),
            'level'      => (int) ($xp->current_level ?? 1),
            'streak'     => (int) (optional($user->streak)->current_streak ?? 0),
            'longest'    => (int) (optional($user->streak)->longest_streak ?? 0),
            'badges'     => $user->badges()->count(),
        ];
    }
}
