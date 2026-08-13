@extends('admin.layouts.app')

@section('panel')
    <div class="row">

        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">

                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('User')</th>
                                    <th>@lang('Plan')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Expired Date')</th>
                                    <th>@lang('Payment Status')</th>
                                    <th>@lang('Purchased At')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subscriptions as $subscription)
                                    <tr>

                                        <td>
                                            <span class="fw-bold">{{ $subscription->user->fullname }}</span>
                                            <br>
                                            <span class="small"> <a href="{{ route('admin.users.detail', $subscription->user_id) }}"><span>@</span>{{ $subscription?->user?->username }}</a> </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ $subscription?->plan?->name ?? '' }}</span>
                                        </td>

                                        <td>
                                            {{ showAmount($subscription->price) }}
                                        </td>

                                        <td>
                                            {{ showDateTime($subscription->expired_date) }} <br> {{ diffForHumans($subscription->expired_date) }}
                                        </td>
                                        <td>
                                            @php
                                                echo $subscription->paymentStatusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            {{ showDateTime($subscription->created_at) }} <br> {{ diffForHumans($subscription->created_at) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table><!-- table end -->
                    </div>
                </div>
                @if ($subscriptions->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($subscriptions) }}
                    </div>
                @endif
            </div><!-- card end -->
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Search Username" dateSearch='yes' />
@endpush
