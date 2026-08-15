<?php

namespace App\Http\Controllers\Website;

use App\Http\Requests\RoomCodeRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizRoom;
use App\Models\QuizRoomParticipant;
use App\Services\QuizAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Multiplayer quiz rooms — Create Room / Join Room.
 *
 * The room lifecycle (waiting → started → completed/cancelled/expired) is
 * managed here; actual play reuses the existing single-player quiz engine
 * (QuizAttemptService) per participant. There is no broadcasting in this
 * project, so the waiting room keeps in sync by polling the `status` endpoint.
 */
class RoomController extends BaseWebsiteController {

    /** How long a freshly created room stays joinable before expiring. */
    const ROOM_TTL_HOURS = 6;

    public function __construct(private QuizAttemptService $attempts) {}

    // --------------------------------------------------------- landing ----

    /** Public "how it works" page — the marketing entry point for rooms. */
    public function landing() {
        $faqs = [
            ['question' => 'What is a Quiz Room on Quiz Mitra?',
             'answer'   => 'A Quiz Room is a private multiplayer game where you and your friends attempt the same quiz together and compare scores on a live leaderboard. The person who creates the room is the host.'],
            ['question' => 'How do I create a quiz room?',
             'answer'   => 'Click “Create a Room”, choose a category and quiz, set the maximum players, and optionally the number of questions and time limit. A unique room code is generated for you to share.'],
            ['question' => 'How do friends join my room?',
             'answer'   => 'Share your room code (for example QZ7K9P). Your friends open “Join a Room”, enter the code, preview the room details, and tap “Join This Room” to enter the live waiting room.'],
            ['question' => 'Is playing a quiz room free?',
             'answer'   => 'Yes. Creating and joining quiz rooms is free — you only need a free Quiz Mitra account to host or join a room.'],
            ['question' => 'Can the host set the number of questions and the time limit?',
             'answer'   => 'Yes. When creating a room the host can choose how many questions to play and a time limit in minutes, or leave them empty to use the quiz’s own defaults.'],
            ['question' => 'How many players can join a room?',
             'answer'   => 'The host sets the maximum number of players (between 2 and 100) while creating the room. Once the room is full, no new players can join.'],
            ['question' => 'What happens after everyone finishes the quiz?',
             'answer'   => 'A live leaderboard ranks every player by score, using finishing time to break ties. Once all players finish, the final results are shown.'],
            ['question' => 'Do I still earn XP and badges in a room?',
             'answer'   => 'Yes. Every quiz you play inside a room awards the same XP and badges as a normal quiz, and counts towards your level and leaderboard rank.'],
        ];

        $seo = $this->seo([
            'title'       => 'Play Live Multiplayer Quizzes with Friends',
            'description' => 'Create a private quiz room, share a code, and compete with friends in real time on Quiz Mitra — with a live leaderboard.',
            'canonical'   => route('website.play.live'),
            'schema'      => [
                $this->faqSchema($faqs),
                $this->breadcrumbSchema([
                    'Home'      => route('home'),
                    'Play Live' => route('website.play.live'),
                ]),
            ],
        ]);

        return view('website.rooms.landing', compact('seo', 'faqs'));
    }

    // ---------------------------------------------------------- create ----

    /** Step 1: the create wizard. Only categories that actually have quizzes. */
    public function create() {
        $categoryIds = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
            ->whereNotNull('category_id')->distinct()->pluck('category_id');

        $categories = Category::whereNull('parent_id')
            ->where('status', 1)
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $seo = $this->seo([
            'title'  => 'Create a Quiz Room — Play Multiplayer Quizzes',
            'robots' => 'noindex, follow',
        ]);

        return view('website.rooms.create', compact('seo', 'categories'));
    }

    /** Step 2 (AJAX): published quizzes for a category. Never trusts the client. */
    public function quizzesByCategory(Request $request) {
        $category = Category::where('id', $request->category_id)->where('status', 1)->first();
        if (!$category) {
            return response()->json(['quizzes' => []]);
        }

        $quizzes = Quiz::where('status', Quiz::STATUS_PUBLISHED)
            ->where('category_id', $category->id)
            ->has('questions')
            ->withCount('questions')
            ->orderBy('title')
            ->get(['id', 'title', 'difficulty', 'time_limit', 'question_limit'])
            ->map(fn($q) => [
                'id'            => $q->id,
                'title'         => $q->title,
                'difficulty'    => ucfirst($q->difficulty),
                'questions'     => $q->effectiveQuestionCount($q->questions_count),
                'time_limit'    => $q->time_limit ? $q->time_limit . ' min' : 'No limit',
                // Bounds/defaults for the room settings step.
                'max_questions' => (int) $q->questions_count,
                'def_questions' => $q->effectiveQuestionCount($q->questions_count),
                'def_time'      => (int) $q->time_limit,
            ]);

        return response()->json(['quizzes' => $quizzes]);
    }

    /** Create the room: verify everything server-side, then commit atomically. */
    public function store(StoreRoomRequest $request) {
        $quiz = Quiz::findOrFail($request->quiz_id);

        $room = DB::transaction(function () use ($request, $quiz) {
            $room = QuizRoom::create([
                'quiz_id'      => $quiz->id,
                'category_id'  => $quiz->category_id,   // taken from the quiz, not the form
                'host_user_id' => auth()->id(),
                'room_code'    => QuizRoom::generateUniqueCode(),
                'status'         => QuizRoom::STATUS_WAITING,
                'max_players'    => $request->max_players,
                'question_count' => $request->filled('question_count') ? (int) $request->question_count : null,
                'time_limit'     => $request->filled('time_limit') ? (int) $request->time_limit : null,
                'room_type'      => $request->room_type ?: QuizRoom::TYPE_PRIVATE,
                'expires_at'     => now()->addHours(self::ROOM_TTL_HOURS),
            ]);

            $room->participants()->create([
                'user_id'   => auth()->id(),
                'role'      => QuizRoomParticipant::ROLE_HOST,
                'status'    => QuizRoomParticipant::STATUS_JOINED,
                'joined_at' => now(),
            ]);

            return $room;
        });

        return redirect()->route('website.rooms.waiting', $room->id);
    }

    // ------------------------------------------------------------ join ----

    public function join() {
        $seo = $this->seo(['title' => 'Join a Quiz Room', 'robots' => 'noindex, follow']);
        return view('website.rooms.join', compact('seo'));
    }

    /** Preview a room from its code before committing to join (AJAX). */
    public function preview(RoomCodeRequest $request) {
        $room = QuizRoom::with(['quiz:id,title', 'category:id,name', 'host:id,firstname,lastname'])
            ->where('room_code', $request->room_code)->first();

        $this->refreshExpiry($room);

        $alreadyIn = $room->participants()
            ->where('user_id', auth()->id())
            ->where('status', '!=', QuizRoomParticipant::STATUS_LEFT)
            ->exists();

        return response()->json([
            'room' => [
                'code'        => $room->room_code,
                'quiz'        => $room->quiz->title,
                'category'    => $room->category->name,
                'host'        => $room->host->fullname,
                'players'     => $room->currentPlayerCount(),
                'max_players' => $room->max_players,
                'status'      => $room->status,
                'already_in'  => $alreadyIn,
                'joinable'    => $room->isJoinable() && (!$room->isFull() || $alreadyIn),
                'message'     => $this->joinBlockMessage($room, $alreadyIn),
            ],
        ]);
    }

    /** Commit the join with capacity + status enforced under a row lock. */
    public function joinStore(RoomCodeRequest $request) {
        $roomId = QuizRoom::where('room_code', $request->room_code)->value('id');

        try {
            $room = DB::transaction(function () use ($roomId) {
                // Lock the room row so concurrent joins cannot both pass the
                // capacity check and overfill the room.
                $room = QuizRoom::whereKey($roomId)->lockForUpdate()->firstOrFail();
                $this->refreshExpiry($room);

                $existing = $room->participants()->where('user_id', auth()->id())->first();

                // Already an active member → just send them back to the room.
                if ($existing && $existing->status !== QuizRoomParticipant::STATUS_LEFT) {
                    throw new RoomJoinRedirect($room, 'You are already in this room.');
                }

                if (!$room->isJoinable()) {
                    throw new \RuntimeException($this->joinBlockMessage($room, false));
                }

                if ($room->activeParticipants()->count() >= $room->max_players) {
                    throw new \RuntimeException('This room is full.');
                }

                // Rejoin after leaving, or first-time join.
                if ($existing) {
                    $existing->update([
                        'status'    => QuizRoomParticipant::STATUS_JOINED,
                        'left_at'   => null,
                        'joined_at' => now(),
                    ]);
                } else {
                    $room->participants()->create([
                        'user_id'   => auth()->id(),
                        'role'      => QuizRoomParticipant::ROLE_PLAYER,
                        'status'    => QuizRoomParticipant::STATUS_JOINED,
                        'joined_at' => now(),
                    ]);
                }

                return $room;
            });
        } catch (RoomJoinRedirect $e) {
            return redirect()->route('website.rooms.waiting', $e->room->id)->with('info', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('website.rooms.waiting', $room->id);
    }

    // --------------------------------------------------------- waiting ----

    public function waiting(QuizRoom $room) {
        Gate::authorize('view', $room);
        $room->load(['quiz:id,title,slug,time_limit', 'category:id,name', 'host:id,firstname,lastname']);
        $this->refreshExpiry($room);

        $isHost = $room->isHost(auth()->user());

        $seo = $this->seo(['title' => 'Quiz Room ' . $room->room_code, 'robots' => 'noindex, nofollow']);

        return view('website.rooms.waiting', compact('seo', 'room', 'isHost'));
    }

    /** Polled by the waiting room to keep the player list and status in sync. */
    public function status(QuizRoom $room) {
        Gate::authorize('view', $room);
        $this->refreshExpiry($room);

        $participants = $room->activeParticipants()
            ->with('user:id,firstname,lastname')
            ->orderBy('role')           // host first
            ->orderBy('joined_at')
            ->get()
            ->map(fn($p) => [
                'name'    => $p->user->fullname,
                'is_host' => $p->role === QuizRoomParticipant::ROLE_HOST,
                'is_you'  => (int) $p->user_id === (int) auth()->id(),
            ]);

        return response()->json([
            'status'      => $room->status,
            'players'     => $room->currentPlayerCount(),
            'max_players' => $room->max_players,
            'is_host'     => $room->isHost(auth()->user()),
            'participants'=> $participants,
            'play_url'    => $room->status === QuizRoom::STATUS_STARTED
                ? route('website.rooms.play', $room->id) : null,
        ]);
    }

    // ------------------------------------------------- host: start/leave --

    public function start(QuizRoom $room) {
        Gate::authorize('start', $room);

        $room->update(['status' => QuizRoom::STATUS_STARTED, 'started_at' => now()]);

        return redirect()->route('website.rooms.play', $room->id);
    }

    /**
     * Enter the actual quiz. Each participant gets their own attempt of the same
     * quiz (reusing the existing engine); the attempt is linked to their room row.
     */
    public function play(QuizRoom $room) {
        Gate::authorize('view', $room);

        if ($room->status !== QuizRoom::STATUS_STARTED) {
            return redirect()->route('website.rooms.waiting', $room->id);
        }

        $participant = $room->participants()->where('user_id', auth()->id())->firstOrFail();
        $room->loadMissing('quiz');

        // Reuse the participant's linked attempt (survives reloads); otherwise
        // start a fresh one that bakes in the room's question count and clock.
        $attempt = $participant->attempt
            ?: $this->attempts->startOrResume(auth()->user(), $room->quiz, [
                'force_new'      => true,
                'question_limit' => $room->question_count,   // null → quiz default
                'time_limit'     => $room->time_limit,       // null → quiz default
            ]);

        $participant->update([
            'status'          => QuizRoomParticipant::STATUS_PLAYING,
            'quiz_attempt_id' => $attempt->id,
        ]);

        return redirect()->route('website.quiz.attempt', $attempt->id);
    }

    // ------------------------------------------------------ leaderboard --

    /** Live room leaderboard, viewable once the quiz has started. */
    public function results(QuizRoom $room) {
        Gate::authorize('view', $room);
        $room->load(['quiz:id,title', 'category:id,name']);

        if ($room->status === QuizRoom::STATUS_WAITING) {
            return redirect()->route('website.rooms.waiting', $room->id);
        }

        $board = $this->buildLeaderboard($room);
        $seo   = $this->seo(['title' => 'Room Results ' . $room->room_code, 'robots' => 'noindex, nofollow']);

        return view('website.rooms.results', compact('seo', 'room', 'board'));
    }

    /** Polled by the leaderboard so it updates live as players finish. */
    public function resultsData(QuizRoom $room) {
        Gate::authorize('view', $room);
        return response()->json($this->buildLeaderboard($room));
    }

    /**
     * Reads each participant's linked attempt, freezes finished scores onto the
     * participant row, ranks everyone, and completes the room once all are done.
     */
    private function buildLeaderboard(QuizRoom $room): array {
        $this->syncScores($room);

        $rows = $room->activeParticipants()
            ->with(['user:id,firstname,lastname', 'attempt'])
            ->get()
            ->map(function ($p) {
                $a = $p->attempt;
                $finished = $a && $a->status === QuizAttempt::STATUS_COMPLETED;
                return [
                    'name'       => $p->user->fullname,
                    'is_host'    => $p->role === QuizRoomParticipant::ROLE_HOST,
                    'is_you'     => (int) $p->user_id === (int) auth()->id(),
                    'finished'   => $finished,
                    'playing'    => $a && !$finished,
                    'score'      => $a ? (float) $a->score : 0,
                    'total'      => $a ? (float) $a->total_marks : 0,
                    'correct'    => $a ? (int) $a->correct_count : 0,
                    'wrong'      => $a ? (int) $a->wrong_count : 0,
                    'percentage' => $a ? round($a->percentage) : 0,
                    'time'       => ($a && $a->time_taken)
                        ? gmdate($a->time_taken >= 3600 ? 'H:i:s' : 'i:s', $a->time_taken) : '—',
                    'time_taken' => $a && $a->time_taken ? $a->time_taken : PHP_INT_MAX,
                ];
            })
            ->sort(function ($a, $b) {
                // Finished first; among finished, higher score then faster time.
                if ($a['finished'] !== $b['finished']) return $b['finished'] <=> $a['finished'];
                if ($a['finished']) {
                    if ($a['score'] != $b['score']) return $b['score'] <=> $a['score'];
                    return $a['time_taken'] <=> $b['time_taken'];
                }
                return $b['playing'] <=> $a['playing'];   // playing before not-started
            })
            ->values();

        $rank = 0;
        $rows = $rows->map(function ($r) use (&$rank) {
            $r['rank'] = $r['finished'] ? ++$rank : null;
            unset($r['time_taken']);
            return $r;
        });

        return [
            'status'   => $room->status,
            'finished' => $rows->where('finished', true)->count(),
            'total'    => $rows->count(),
            'rows'     => $rows->all(),
        ];
    }

    /** Copies completed attempts onto participant rows and closes the room. */
    private function syncScores(QuizRoom $room): void {
        $participants = $room->activeParticipants()->with('attempt')->get();

        foreach ($participants as $p) {
            if ($p->attempt
                && $p->attempt->status === QuizAttempt::STATUS_COMPLETED
                && $p->status !== QuizRoomParticipant::STATUS_FINISHED) {
                $p->update([
                    'status'          => QuizRoomParticipant::STATUS_FINISHED,
                    'score'           => $p->attempt->score,
                    'correct_answers' => $p->attempt->correct_count,
                    'wrong_answers'   => $p->attempt->wrong_count,
                    'completed_at'    => $p->attempt->submitted_at ?? now(),
                ]);
            }
        }

        if ($room->status === QuizRoom::STATUS_STARTED) {
            $active   = $room->activeParticipants()->count();
            $finished = $room->activeParticipants()->where('status', QuizRoomParticipant::STATUS_FINISHED)->count();
            if ($active > 0 && $finished === $active) {
                $room->update(['status' => QuizRoom::STATUS_COMPLETED, 'ended_at' => now()]);
            }
        }
    }

    public function leave(QuizRoom $room) {
        Gate::authorize('leave', $room);

        $participant = $room->participants()->where('user_id', auth()->id())->first();
        if ($participant) {
            $participant->update(['status' => QuizRoomParticipant::STATUS_LEFT, 'left_at' => now()]);
        }

        // If the host leaves while waiting, the room is cancelled for everyone.
        if ($room->isHost(auth()->user()) && $room->status === QuizRoom::STATUS_WAITING) {
            $room->update(['status' => QuizRoom::STATUS_CANCELLED, 'ended_at' => now()]);
        }

        return redirect()->route('website.rooms.join')->with('info', 'You have left the room.');
    }

    // ----------------------------------------------------------- helpers --

    /** Lazily flip a room to "expired" once its TTL passes. */
    private function refreshExpiry(QuizRoom $room): void {
        if ($room->status === QuizRoom::STATUS_WAITING && $room->expires_at && $room->expires_at->isPast()) {
            $room->update(['status' => QuizRoom::STATUS_EXPIRED]);
        }
    }

    private function joinBlockMessage(QuizRoom $room, bool $alreadyIn): ?string {
        if ($alreadyIn) return 'You are already in this room.';
        return match ($room->status) {
            QuizRoom::STATUS_STARTED   => 'This quiz has already started.',
            QuizRoom::STATUS_COMPLETED => 'This quiz room has finished.',
            QuizRoom::STATUS_CANCELLED => 'This room was cancelled by the host.',
            QuizRoom::STATUS_EXPIRED   => 'This room has expired.',
            default => $room->isFull() ? 'This room is full.' : null,
        };
    }
}

/** Internal control-flow signal: an active member re-opening the room. */
class RoomJoinRedirect extends \Exception {
    public function __construct(public QuizRoom $room, string $message) {
        parent::__construct($message);
    }
}
