@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="" method="GET">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Search topic')</label>
                                    <input type="text" name="search" class="form-control" value="{{ request()->search }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Category')</label>
                                    <select name="category_id" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ __($category->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Status')</label>
                                    <select name="status" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach ([
                                            'pending' => 'Pending', 'generating' => 'Generating',
                                            'completed' => 'Completed', 'partially_completed' => 'Partially Completed',
                                            'failed' => 'Failed', 'cancelled' => 'Cancelled',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') == $value)>@lang($label)</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn--primary h-45 flex-grow-1"><i class="las la-search"></i></button>
                                <a href="{{ route('admin.ai-generator.history') }}" class="btn btn--dark h-45 flex-grow-1"><i class="las la-undo"></i></a>
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
                                    <th>@lang('ID')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Topic')</th>
                                    <th>@lang('Generated')</th>
                                    <th>@lang('Published')</th>
                                    <th>@lang('Rejected')</th>
                                    <th>@lang('Duplicate')</th>
                                    <th>@lang('Provider / Model')</th>
                                    <th>@lang('Tokens')</th>
                                    <th>@lang('Cost')</th>
                                    <th>@lang('Created By')</th>
                                    <th>@lang('Date')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($generations as $generation)
                                    <tr>
                                        <td>#{{ $generation->id }}</td>
                                        <td>
                                            <span class="d-block">{{ $generation->category?->name ?? '-' }}</span>
                                            <small class="text-muted">{{ $generation->subCategory?->name ?? '-' }}</small>
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit($generation->topic, 30) ?: '-' }}</td>
                                        <td>{{ $generation->generated_count }} / {{ $generation->requested_count }}</td>
                                        <td><span class="badge badge--success">{{ $generation->published_count }}</span></td>
                                        <td><span class="badge badge--danger">{{ $generation->rejected_count }}</span></td>
                                        <td><span class="badge badge--warning">{{ $generation->duplicate_count }}</span></td>
                                        <td>
                                            <span class="d-block">{{ $generation->provider }}</span>
                                            <small class="text-muted">{{ $generation->model }}</small>
                                        </td>
                                        <td>{{ $generation->total_tokens ? number_format($generation->total_tokens) : '-' }}</td>
                                        <td>{{ $generation->estimated_cost !== null ? '$' . number_format($generation->estimated_cost, 4) : '-' }}</td>
                                        <td>{{ $generation->creator?->name ?? '-' }}</td>
                                        <td>{{ showDateTime($generation->created_at) }}</td>
                                        <td>
                                            @php echo $generation->statusBadge; @endphp
                                            @if ($generation->error_message)
                                                <i class="las la-info-circle text--danger" title="{{ $generation->error_message }}"></i>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.ai-generator.preview', $generation->id) }}" class="btn btn--sm btn-outline--primary">
                                                    @if ($generation->isReviewable() && $generation->generated_count > $generation->published_count + $generation->rejected_count)
                                                        <i class="la la-tasks"></i> @lang('Continue Review')
                                                    @else
                                                        <i class="la la-eye"></i> @lang('View')
                                                    @endif
                                                </a>
                                                <button class="btn btn--sm btn-outline--info" type="button" data-bs-toggle="dropdown"><i class="las la-ellipsis-v"></i></button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{ route('admin.ai-generator.raw', $generation->id) }}" target="_blank">
                                                        <i class="la la-code"></i> @lang('Raw Response')
                                                    </a>
                                                    @if (!$generation->isFailed() && $generation->status != 'completed')
                                                        <button class="dropdown-item confirmationBtn"
                                                                data-action="{{ route('admin.ai-generator.cancel', $generation->id) }}"
                                                                data-question="@lang('Cancel this generation?')">
                                                            <i class="la la-ban"></i> @lang('Cancel')
                                                        </button>
                                                    @endif
                                                    <button class="dropdown-item confirmationBtn"
                                                            data-action="{{ route('admin.ai-generator.delete', $generation->id) }}"
                                                            data-question="@lang('Delete this generation and its generated questions? Questions already imported into the Question Bank are kept.')">
                                                        <i class="la la-trash"></i> @lang('Delete')
                                                    </button>
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
                @if ($generations->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($generations) }}</div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.ai-generator.create') }}">
        <i class="las la-magic"></i>@lang('New Generation')
    </a>
@endpush
