@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5>User XP Management</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total XP</th>
                            <th>Level</th>
                            <th>This Week</th>
                            <th>This Month</th>
                            <th>Streak</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>{{ $user->user->firstname }} {{ $user->user->lastname }}</td>
                            <td>{{ number_format($user->total_xp) }}</td>
                            <td><span class="badge badge-primary">{{ $user->current_level }}</span></td>
                            <td>{{ number_format($user->xp_this_week) }}</td>
                            <td>{{ number_format($user->xp_this_month) }}</td>
                            <td>{{ $user->user->streak->current_streak ?? 0 }} days</td>
                            <td>{{ $user->last_xp_activity ? $user->last_xp_activity->diffForHumans() : 'Never' }}</td>
                            <td>
                                <a href="{{ route('admin.xp.users.show', $user->user_id) }}" class="btn btn-sm btn-info">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
