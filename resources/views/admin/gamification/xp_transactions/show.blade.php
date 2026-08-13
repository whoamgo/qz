@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>Transaction Details #{{ $transaction->id }}</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td><strong>User</strong></td>
                        <td>{{ $transaction->user->firstname }} {{ $transaction->user->lastname }} (#{{ $transaction->user->id }})</td>
                    </tr>
                    <tr>
                        <td><strong>Event Type</strong></td>
                        <td><code>{{ $transaction->event_type }}</code></td>
                    </tr>
                    <tr>
                        <td><strong>Reference</strong></td>
                        <td>{{ ucfirst($transaction->reference_type) }} #{{ $transaction->reference_id }}</td>
                    </tr>
                    <tr>
                        <td><strong>XP Amount</strong></td>
                        <td><strong>{{ $transaction->xp_amount }}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Direction</strong></td>
                        <td>
                            @if($transaction->direction == 'earned')
                                <span class="badge badge-success">Earned</span>
                            @else
                                <span class="badge badge-danger">Deducted</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Source</strong></td>
                        <td>{{ ucfirst($transaction->source) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Description</strong></td>
                        <td>{{ $transaction->description }}</td>
                    </tr>
                    @if($transaction->admin_id)
                    <tr>
                        <td><strong>Admin</strong></td>
                        <td>{{ $transaction->admin->firstname ?? 'N/A' }} (ID: {{ $transaction->admin_id }})</td>
                    </tr>
                    @endif
                    @if($transaction->admin_note)
                    <tr>
                        <td><strong>Admin Note</strong></td>
                        <td>{{ $transaction->admin_note }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>Date</strong></td>
                        <td>{{ $transaction->created_at->format('M d, Y H:i:s') }}</td>
                    </tr>
                </table>

                @if($transaction->metadata)
                <hr>
                <h6>Metadata</h6>
                <pre>{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif

                <div class="mt-4">
                    <a href="{{ route('admin.xp.transactions.index') }}" class="btn btn-secondary">Back to Transactions</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
