@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Badges</h5>
                <a href="{{ route('admin.xp.badges.create') }}" class="btn btn-primary">Add Badge</a>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Condition</th>
                            <th>Reward XP</th>
                            <th>Times Earned</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($badges as $badge)
                        <tr>
                            <td>{{ $badge->name }}</td>
                            <td><code>{{ $badge->slug }}</code></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $badge->condition_type)) }}</td>
                            <td>{{ $badge->reward_xp }}</td>
                            <td>{{ $badge->times_earned }}</td>
                            <td>
                                @if($badge->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.xp.badges.edit', $badge->id) }}" class="btn btn-sm btn-info">Edit</a>
                                <form action="{{ route('admin.xp.badges.delete', $badge->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $badges->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
