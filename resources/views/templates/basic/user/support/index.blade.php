@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="topbar">
                <h5 class="topbar__title mb-0">{{ __($pageTitle) }}</h5>
                <a href="{{ route('ticket.open') }}" class="btn btn--sm btn--base mb-2">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    @lang('New Ticket')</a>
            </div>
            <div class="table--responsive">
                <table class="table table-responsive--lg">
                    <thead>
                        <tr>
                            <th>@lang('Subject')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Priority')</th>
                            <th>@lang('Last Reply')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supports as $support)
                            <tr>
                                <td> <a href="{{ route('ticket.view', $support->ticket) }}" class="fw-bold text--base"> [@lang('Ticket')#{{ $support->ticket }}] {{ __($support->subject) }} </a></td>
                                <td>
                                    @php echo $support->statusBadge; @endphp
                                </td>
                                <td>
                                    @php echo $support->priorityBadge; @endphp
                                </td>
                                <td>{{ diffForHumans($support->last_reply) }} </td>

                                <td>
                                    <a href="{{ route('ticket.view', $support->ticket) }}" class="btn btn--base btn--sm">
                                        <i class="las la-lg la-desktop"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($supports->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($supports) }}
                </div>
            @endif
        </div>
    </div>
@endsection
