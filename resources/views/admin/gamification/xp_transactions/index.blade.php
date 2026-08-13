@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5>XP Transactions</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Event</th>
                            <th>XP</th>
                            <th>Direction</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                        <tr>
                            <td>#{{ $transaction->id }}</td>
                            <td>{{ $transaction->user->firstname }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $transaction->event_type)) }}</td>
                            <td>{{ $transaction->xp_amount }}</td>
                            <td>
                                @if($transaction->direction == 'earned')
                                    <span class="badge badge-success">Earned</span>
                                @else
                                    <span class="badge badge-danger">Deducted</span>
                                @endif
                            </td>
                            <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
