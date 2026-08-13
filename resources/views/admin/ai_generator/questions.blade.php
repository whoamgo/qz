@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="" method="GET">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Search')</label>
                                    <input type="text" name="search" class="form-control" value="{{ request()->search }}" placeholder="@lang('Question text...')">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Status')</label>
                                    <select name="status" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach ([
                                            'pending_review' => 'Pending Review', 'approved' => 'Approved',
                                            'published' => 'Published', 'rejected' => 'Rejected', 'duplicate' => 'Duplicate',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') == $value)>@lang($label)</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn--primary h-45 flex-grow-1"><i class="las la-search"></i></button>
                                <a href="{{ route('admin.ai-generator.questions') }}" class="btn btn--dark h-45 flex-grow-1"><i class="las la-undo"></i></a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Gen')</th>
                                    <th>@lang('Question')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Answer')</th>
                                    <th>@lang('Difficulty')</th>
                                    <th>@lang('Duplicate')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Bank ID')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($questions as $question)
                                    <tr>
                                        <td>#{{ $question->generation_id }}</td>
                                        <td style="max-width:340px;">
                                            <span class="d-block">{{ \Illuminate\Support\Str::limit($question->question, 90) }}</span>
                                            @if ($question->validation_errors)
                                                <small class="text--danger">{{ $question->validation_errors }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="d-block">{{ $question->generation?->category?->name ?? '-' }}</span>
                                            <small class="text-muted">{{ $question->generation?->subCategory?->name ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge--success">{{ $question->correct_answer }}</span>
                                            <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($question->correctOptionText(), 24) }}</small>
                                        </td>
                                        <td>{{ ucfirst($question->difficulty) }}</td>
                                        <td>
                                            @if ($question->duplicate_flag)
                                                <span class="badge badge--warning">{{ $question->similarity_score }}%</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>@php echo $question->statusBadge; @endphp</td>
                                        <td>{{ $question->question_id ? '#' . $question->question_id : '-' }}</td>
                                        <td>
                                            <a href="{{ route('admin.ai-generator.preview', $question->generation_id) }}" class="btn btn--sm btn-outline--primary">
                                                <i class="la la-eye"></i> @lang('Review')
                                            </a>
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
                @if ($questions->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($questions) }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
