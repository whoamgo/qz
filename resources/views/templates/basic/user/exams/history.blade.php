@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="topbar">
                <h5 class="topbar__title mb-0">{{ __($pageTitle) }}</h5>
                <form class="search-form2">
                    <div class="input-group input--group border--gradient">
                        <input type="text" name="search" class="form-control form--control" value="{{ request()->search }}" placeholder="@lang('Search here')..." id="search" />
                        <button class="input-group-text">
                            <svg
                                 xmlns="http://www.w3.org/2000/svg"
                                 width="20"
                                 height="20"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34"></path>
                                <circle cx="11" cy="11" r="8"></circle>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
            <div class="table--responsive">
                <table class="table table-responsive--lg">
                    <thead>
                        <tr>
                            <th>@lang('Exam Title')</th>
                            <th>@lang('Start Time')</th>
                            <th>@lang('End Time')</th>
                            <th>@lang('Pass Percentage')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($examLogs as $examLog)
                            <tr>
                                <td>
                                    <div>
                                        <span class="fw-bold text--base">
                                            {{ __($examLog?->exam?->title ?? '') }}
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span>
                                        {{ showDateTime($examLog->start_time) }}
                                    </span>
                                </td>
                                <td>
                                    <span>
                                        {{ showDateTime($examLog->end_time) }}
                                    </span>
                                </td>

                                <td>
                                    <span>
                                        {{ getAmount($examLog->pass_percentage) }}%
                                    </span>
                                </td>

                                <td>
                                    @php echo $examLog->statusBadge @endphp
                                </td>

                                <td>
                                    <a href="{{ route('user.exam.view', $examLog->id) }}" class="btn btn--base btn--xsm" data-bs-toggle="tooltip" data-bs-title="@lang('Details')">
                                        <i class="las la-lg la-desktop"></i>
                                    </a>
                                    @if ($examLog->status == Status::EXAM_COMPLETED && $examLog->exam->isPaid() && $examLog->pass_percentage >= $examLog->exam->pass_percentage)
                                        <a href="{{ route('user.exam.certificate.download', $examLog->id) }}" class="btn btn--info btn--xsm" data-bs-toggle="tooltip" data-bs-title="@lang('Certificate')">
                                            <i class="las la-lg la-certificate"></i>
                                        </a>
                                    @endif
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
            @if ($examLogs->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($examLogs) }}
                </div>
            @endif
        </div>
    </div>
@endsection
