@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Levels</h5>
                <a href="{{ route('admin.xp.levels.create') }}" class="btn btn-primary">Add Level</a>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Name</th>
                            <th>Required XP</th>
                            <th>Reward XP</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($levels as $level)
                        <tr>
                            <td><strong>{{ $level->level_number }}</strong></td>
                            <td>{{ $level->name }}</td>
                            <td>{{ number_format($level->required_xp) }}</td>
                            <td>{{ $level->reward_xp }}</td>
                            <td>
                                @if($level->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.xp.levels.edit', $level->id) }}" class="btn btn-sm btn-info">Edit</a>
                                <form action="{{ route('admin.xp.levels.delete', $level->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $levels->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
