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
                                            <span class="fw-bold">{{ $examLog?->user?->fullname ?? '' }}</span>
                                            <br>
                                            <span class="small"> <a href="{{ route('admin.users.detail', $examLog->user_id) }}"><span>@</span>{{ $examLog?->user?->username }}</a> </span>
                                        </td>
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
                                            <a href="{{ route('admin.report.exam.view', $examLog->id) }}" class="btn btn-outline--primary btn-sm">
                                                <i class="las la-desktop"></i> @lang('Details')
                                            </a>

                                            @if ($examLog->status == Status::EXAM_COMPLETED)
                                                <button class="btn btn-sm btn-outline--success previewBtn" data-action="{{ route('admin.certificate.preview', $examLog->id) }}" class="dropdown-item">
                                                    <i class="las la-certificate"></i>@lang('Certificate ')
                                                </button>
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
                </div>
                @if ($examLogs->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($examLogs) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Search Username" />
@endpush
@push('script')
    <script>
        $('.previewBtn').on('click', function() {
            let url = $(this).data('action');
            window.open(url, '_blank', 'noopener,noreferrer,width=900,height=600');
        })
    </script>
@endpush
