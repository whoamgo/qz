@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5>{{ $user->firstname }} {{ $user->lastname }} - XP Profile</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>Total XP</h6>
                                <h3>{{ number_format($userXp->total_xp) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>Current Level</h6>
                                <h3>{{ $userXp->current_level }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>This Week</h6>
                                <h3>{{ number_format($userXp->xp_this_week) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>Streak</h6>
                                <h3>{{ $streak?->current_streak ?? 0 }} days</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5>Badges Earned ({{ $badges->count() }})</h5>
            </div>
            <div class="card-body">
                @if($badges->count())
                    <div class="row">
                        @foreach($badges as $badge)
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div style="font-size: 30px;">{{ $badge->icon ?? '🏅' }}</div>
                                    <h6>{{ $badge->name }}</h6>
                                    <small class="text-muted">{{ $badge->pivot->earned_at->format('M d, Y') }}</small>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">No badges earned yet</p>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5>XP Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form action="{{ route('admin.xp.users.add.xp', $user->id) }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label>Add XP</label>
                                <input type="number" name="amount" class="form-control" placeholder="Amount" required>
                            </div>
                            <div class="form-group">
                                <label>Reason <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control" placeholder="e.g., Bonus for referral" required>
                            </div>
                            <div class="form-group">
                                <label>Admin Note</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="Optional note"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">Add XP</button>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <form action="{{ route('admin.xp.users.deduct.xp', $user->id) }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label>Deduct XP</label>
                                <input type="number" name="amount" class="form-control" placeholder="Amount" required>
                            </div>
                            <div class="form-group">
                                <label>Reason <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control" placeholder="e.g., Cheating detection" required>
                            </div>
                            <div class="form-group">
                                <label>Admin Note</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="Optional note"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger">Deduct XP</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Recent XP Transactions</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Event</th>
                            <th>XP</th>
                            <th>Direction</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTransactions as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $transaction->event_type)) }}</td>
                            <td>{{ $transaction->xp_amount }}</td>
                            <td>
                                @if($transaction->direction == 'earned')
                                    <span class="badge badge-success">Earned</span>
                                @else
                                    <span class="badge badge-danger">Deducted</span>
                                @endif
                            </td>
                            <td>{{ $transaction->description }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
