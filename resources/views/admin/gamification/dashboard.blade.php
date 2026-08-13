@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <h3>XP Dashboard</h3>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Total XP Awarded</h5>
                <h2>{{ number_format($totalXpStats['total_awarded'] ?? 0) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Today</h5>
                <h2>{{ number_format($totalXpStats['today'] ?? 0) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>This Week</h5>
                <h2>{{ number_format($totalXpStats['this_week'] ?? 0) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>This Month</h5>
                <h2>{{ number_format($totalXpStats['this_month'] ?? 0) }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Total Users with XP</h5>
                <h2>{{ $totalUsersWithXp }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Average XP per User</h5>
                <h2>{{ number_format($averageXpPerUser) }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5>Top 10 Users</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>User</th>
                            <th>XP</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topUsers as $key => $userXp)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $userXp->user->firstname }} {{ $userXp->user->lastname }}</td>
                            <td>{{ number_format($userXp->total_xp) }}</td>
                            <td><span class="badge badge-primary">Level {{ $userXp->current_level }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5>Recent Transactions</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Event</th>
                            <th>XP</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTransactions as $transaction)
                        <tr>
                            <td>{{ $transaction->user->firstname }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $transaction->event_type)) }}</td>
                            <td>
                                @if($transaction->direction == 'earned')
                                    <span class="text-success">+{{ $transaction->xp_amount }}</span>
                                @else
                                    <span class="text-danger">-{{ $transaction->xp_amount }}</span>
                                @endif
                            </td>
                            <td>{{ $transaction->created_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5>Quick Actions</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.xp.rules.index') }}" class="btn btn-primary">Manage XP Rules</a>
                <a href="{{ route('admin.xp.levels.index') }}" class="btn btn-info">Manage Levels</a>
                <a href="{{ route('admin.xp.users.index') }}" class="btn btn-warning">View User XP</a>
                <a href="{{ route('admin.xp.transactions.index') }}" class="btn btn-secondary">View Transactions</a>
            </div>
        </div>
    </div>
</div>
@endsection
