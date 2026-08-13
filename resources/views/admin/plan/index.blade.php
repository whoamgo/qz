@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Monthly Price')</th>
                                    <th>@lang('Yearly Price')</th>
                                    <th>@lang('Exam Limit')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($plans as $plan)
                                    <tr>
                                        <td>
                                            <span>{{ __($plan->name) }}</span>
                                        </td>
                                        <td>
                                            <span>{{ showAmount($plan->monthly_price) }}</span>
                                        </td>
                                        <td>
                                            <span>{{ showAmount($plan->yearly_price) }}</span>
                                        </td>
                                        <td>
                                            <span>@lang('Monthly') : {{ getAmount($plan->monthly_exam) }}</span><br>
                                            <span>@lang('Yearly') : {{ getAmount($plan->yearly_exam) }}</span>
                                        </td>
                                        <td>
                                            @php
                                                echo $plan->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <a class="btn btn-sm btn-outline--primary" href="{{ route('admin.plan.add', $plan->id) }}">
                                                    <i class="la la-pencil"></i> @lang('Edit')
                                                </a>
                                                @if ($plan->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger confirmationBtn" data-action="{{ route('admin.plan.status', $plan->id) }}" data-question="@lang('Are you sure to disable this plan')?">
                                                        <i class="la la-eye-slash"></i> @lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success confirmationBtn" data-action="{{ route('admin.plan.status', $plan->id) }}" data-question="@lang('Are you sure to enable this plan')?">
                                                        <i class="la la-eye"></i> @lang('Enable')
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($plans->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($plans) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
    <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.plan.add') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
