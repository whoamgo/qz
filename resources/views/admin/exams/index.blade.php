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
                                    <th>@lang('Title')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Difficulty')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Questions')</th>
                                    <th>@lang('Duration')</th>
                                    <th>@lang('Result Date')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($exams as $exam)
                                    <tr>
                                        <td>
                                            <span>{{ __($exam->title) }}</span>
                                        </td>
                                        <td>
                                            <span>{{ __($exam?->category?->name ?? '') }}</span>
                                        </td>
                                        <td>
                                            @php echo $exam->difficultyBadge; @endphp
                                        </td>
                                        <td>
                                            @php echo $exam->typeBadge; @endphp
                                        </td>
                                        <td>
                                            <span>{{ __($exam->question_quantity) }}</span>
                                        </td>
                                        <td>
                                            <span>{{ __($exam->duration) }} @lang('Minutes')</span>
                                        </td>
                                        <td>
                                            <span>{{ showDateTime($exam->result_published_at, 'Y-m-d h:i A') }}</span>
                                        </td>
                                        <td>
                                            @php
                                                echo $exam->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn--sm btn-outline--info" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="las la-ellipsis-v"></i> @lang('Actions')
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{ route('admin.exams.questions', $exam->id) }}">
                                                        <i class="la la-cogs"></i> @lang('Questions')
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('admin.exams.add', $exam->id) }}">
                                                        <i class="la la-pencil"></i> @lang('Edit')
                                                    </a>
                                                    @if ($exam->status == Status::ENABLE)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.status', $exam->id) }}" data-question="@lang('Are you sure to disable this exam')?">
                                                            <i class="la la-eye-slash"></i> @lang('Disable')
                                                        </button>
                                                    @else
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.status', $exam->id) }}" data-question="@lang('Are you sure to enable this exam')?">
                                                            <i class="la la-eye"></i> @lang('Enable')
                                                        </button>
                                                    @endif
                                                    @if ($exam->result_published_at < now() && $exam->result_published == Status::NO)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.publish.result', $exam->id) }}" data-question="@lang('Are you sure to declare the result')?">
                                                            <i class="las la-gavel"></i> @lang('Declare Result')
                                                        </button>
                                                    @endif
                                                </div>
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
                @if ($exams->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($exams) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
    @if (!request()->routeIs('admin.exams.result.declare'))
        <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.exams.add') }}"><i class="las la-plus"></i>@lang('Add New')</a>
    @endif
@endpush
