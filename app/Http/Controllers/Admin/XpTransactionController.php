<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class XpTransactionController extends Controller {
    public function index(Request $request): View {
        $pageTitle = "XP Transactions";

        $query = XpTransaction::with('user');

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('event_type') && $request->event_type) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->has('direction') && $request->direction) {
            $query->where('direction', $request->direction);
        }

        if ($request->has('source') && $request->source) {
            $query->where('source', $request->source);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = "%{$request->search}%";
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', $search)
                  ->orWhere('firstname', 'like', $search)
                  ->orWhere('lastname', 'like', $search);
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(getPaginate());

        $eventTypes = $this->getEventTypes();
        $directions = ['earned' => 'Earned', 'deducted' => 'Deducted'];
        $sources = ['system' => 'System', 'admin' => 'Admin', 'user' => 'User'];

        return view('admin.gamification.xp_transactions.index', compact(
            'pageTitle',
            'transactions',
            'eventTypes',
            'directions',
            'sources'
        ));
    }

    public function show($id): View {
        $pageTitle = "Transaction Details";
        $transaction = XpTransaction::with(['user', 'admin'])->findOrFail($id);

        return view('admin.gamification.xp_transactions.show', compact(
            'pageTitle',
            'transaction'
        ));
    }

    protected function getEventTypes() {
        $types = XpTransaction::selectRaw('DISTINCT event_type')
            ->orderBy('event_type')
            ->pluck('event_type')
            ->toArray();

        return array_combine($types, $types);
    }
}
