<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Level;
use App\Models\User;
use App\Models\UserXp;
use App\Models\XpTransaction;
use App\Services\XpService;
use Illuminate\View\View;

class XpDashboardController extends Controller {
    protected $xpService;

    public function __construct() {
        $this->xpService = new XpService();
    }

    public function index(): View {
        $pageTitle = "XP Dashboard";

        // Total XP Stats
        $totalXpStats = $this->xpService->getTotalXpStats();

        // User XP Stats
        $totalUsersWithXp = UserXp::where('total_xp', '>', 0)->count();
        $averageXpPerUser = $totalUsersWithXp > 0
            ? (int) (XpTransaction::where('direction', 'earned')->sum('xp_amount') / $totalUsersWithXp)
            : 0;

        // Top Users
        $topUsers = UserXp::with('user')
            ->where('total_xp', '>', 0)
            ->orderBy('total_xp', 'desc')
            ->limit(10)
            ->get();

        // Highest XP User
        $highestXpUser = $topUsers->first();

        // Recent Transactions
        $recentTransactions = XpTransaction::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        // XP Activity Chart Data
        $activityChartData = $this->getActivityChartData();

        // XP Distribution Chart Data
        $distributionChartData = $this->getDistributionChartData();

        // Level Distribution
        $levelDistribution = UserXp::selectRaw('current_level, COUNT(*) as count')
            ->where('total_xp', '>', 0)
            ->groupBy('current_level')
            ->orderBy('current_level')
            ->get();

        return view('admin.gamification.dashboard', compact(
            'pageTitle',
            'totalXpStats',
            'totalUsersWithXp',
            'averageXpPerUser',
            'topUsers',
            'highestXpUser',
            'recentTransactions',
            'activityChartData',
            'distributionChartData',
            'levelDistribution'
        ));
    }

    protected function getActivityChartData() {
        $last7Days = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            $xp = XpTransaction::where('direction', 'earned')
                ->whereDate('created_at', $date->toDateString())
                ->sum('xp_amount');

            $last7Days[] = $xp;
        }

        return [
            'labels' => $labels,
            'data' => $last7Days,
        ];
    }

    protected function getDistributionChartData() {
        $distributions = XpTransaction::selectRaw('event_type, COUNT(*) as count, SUM(xp_amount) as total_xp')
            ->where('direction', 'earned')
            ->whereDate('created_at', today())
            ->groupBy('event_type')
            ->orderByDesc('total_xp')
            ->limit(10)
            ->get();

        return [
            'labels' => $distributions->pluck('event_type')->toArray(),
            'data' => $distributions->pluck('total_xp')->toArray(),
        ];
    }
}
