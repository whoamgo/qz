<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserXp;
use App\Models\XpTransaction;
use App\Services\XpService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserXpController extends Controller {
    protected $xpService;

    public function __construct() {
        $this->xpService = new XpService();
    }

    public function index(Request $request): View {
        $pageTitle = "User XP Management";

        $query = UserXp::with('user');

        if ($request->has('search') && $request->search) {
            $search = "%{$request->search}%";
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', $search)
                  ->orWhere('firstname', 'like', $search)
                  ->orWhere('lastname', 'like', $search);
            });
        }

        if ($request->has('level') && $request->level) {
            $query->where('current_level', $request->level);
        }

        $sortBy = $request->get('sort_by', 'total_xp');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(getPaginate());
        $levels = $this->getLevels();

        return view('admin.gamification.user_xp.index', compact(
            'pageTitle',
            'users',
            'levels'
        ));
    }

    public function show($id): View {
        $pageTitle = "User XP Details";
        $user = User::findOrFail($id);
        $userXp = $user->xpProfile ?? UserXp::firstOrCreate(['user_id' => $user->id]);
        $recentTransactions = XpTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $badges = $user->badges()
            ->wherePivot('user_id', $user->id)
            ->get();

        $streak = $user->streak;

        return view('admin.gamification.user_xp.show', compact(
            'pageTitle',
            'user',
            'userXp',
            'recentTransactions',
            'badges',
            'streak'
        ));
    }

    public function addXp(Request $request, $id) {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $adminId = auth()->guard('admin')->id();

        $this->xpService->awardXp(
            $user,
            'admin_award',
            'manual',
            null,
            $request->note,
            'admin',
            $adminId
        );

        $notify[] = ['success', $request->amount . ' XP added to user successfully'];
        return back()->withNotify($notify);
    }

    public function deductXp(Request $request, $id) {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $adminId = auth()->guard('admin')->id();

        $this->xpService->deductXp(
            $user,
            $request->amount,
            $request->reason,
            $request->note,
            $adminId
        );

        $notify[] = ['success', $request->amount . ' XP deducted from user successfully'];
        return back()->withNotify($notify);
    }

    public function resetXp(Request $request, $id) {
        $user = User::findOrFail($id);
        $userXp = $user->xpProfile;

        if ($userXp) {
            $amount = $userXp->total_xp;
            $adminId = auth()->guard('admin')->id();

            $this->xpService->deductXp(
                $user,
                $amount,
                'XP Reset',
                'Admin reset user XP to 0',
                $adminId
            );
        }

        $notify[] = ['success', 'User XP reset successfully'];
        return back()->withNotify($notify);
    }

    protected function getLevels() {
        return \App\Models\Level::active()->orderBy('level_number')->get();
    }
}
