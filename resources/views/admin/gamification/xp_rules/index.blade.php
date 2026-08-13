@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>XP Rules</h5>
                <a href="{{ route('admin.xp.rules.create') }}" class="btn btn-primary">Add New Rule</a>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Category</th>
                            <th>XP Value</th>
                            <th>Daily Limit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr>
                            <td>{{ $rule->name }}</td>
                            <td><code>{{ $rule->key }}</code></td>
                            <td><span class="badge badge-info">{{ ucfirst($rule->category) }}</span></td>
                            <td>{{ $rule->xp_value }}</td>
                            <td>{{ $rule->daily_limit ?? '∞' }}</td>
                            <td>
                                @if($rule->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.xp.rules.edit', $rule->id) }}" class="btn btn-sm btn-info">Edit</a>
                                <form action="{{ route('admin.xp.rules.delete', $rule->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $rules->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
