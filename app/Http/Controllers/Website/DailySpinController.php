<?php

namespace App\Http\Controllers\Website;

use App\Services\DailySpinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AJAX endpoints for Daily Spin & Win. All responses are JSON.
 *
 *   GET  daily-spin/status  (public)  — guest vs available vs completed
 *   POST daily-spin/spin    (auth)    — resolve today's spin + question
 *   POST daily-spin/answer  (auth)    — grade the answer, award XP if correct
 *
 * spin/answer are behind the `auth` middleware (see routes/website.php), so guests
 * can never reach them; the frontend never decides eligibility, result or reward.
 */
class DailySpinController extends BaseWebsiteController
{
    private DailySpinService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DailySpinService();
    }

    /** Public: lets the homepage render the right state without exposing anything. */
    public function status(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success'       => true,
                'authenticated' => false,
                'state'         => 'guest',
                'enabled'       => $this->service->isEnabled(),
                'reward_xp'     => $this->service->rewardXp(),
            ]);
        }

        return response()->json([
            'success'       => true,
            'authenticated' => true,
            'enabled'       => $this->service->isEnabled(),
            'reward_xp'     => $this->service->rewardXp(),
        ] + $this->service->getStatus($user));
    }

    /** Auth: create/resume today's spin and return the question (no answer key). */
    public function spin(): JsonResponse
    {
        $result = $this->service->spin(auth()->user());

        if (($result['state'] ?? null) === 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Daily Spin is currently unavailable.',
            ], 422);
        }

        return response()->json(['success' => true] + $result);
    }

    /** Auth: grade the whole 10-question set server-side and award +5 per correct. */
    public function answer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spin_id'   => ['required', 'integer'],
            'answers'   => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'integer'],
        ]);

        // Keys are question ids; cast to int => int.
        $answers = [];
        foreach ($data['answers'] as $qid => $optId) {
            $answers[(int) $qid] = (int) $optId;
        }

        $result = $this->service->submit(
            auth()->user(),
            (int) $data['spin_id'],
            $answers
        );

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'],
                'message' => $result['message'] ?? 'Unable to submit your answer.',
            ], 422);
        }

        return response()->json(['success' => true] + $result);
    }
}
