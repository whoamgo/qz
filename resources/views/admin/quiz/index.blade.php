@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="" method="GET">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Search')</label>
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" value="{{ request()->search }}" placeholder="@lang('Title / Slug / Description')">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Category')</label>
                                    <select name="category_id" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ __($category->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Sub Category')</label>
                                    <select name="sub_category_id" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach($subCategories as $sub)
                                            <option value="{{ $sub->id }}" @selected(request('sub_category_id') == $sub->id)>{{ __($sub->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Status')</label>
                                    <select name="status" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        <option value="draft" @selected(request('status') == 'draft')>@lang('Draft')</option>
                                        <option value="published" @selected(request('status') == 'published')>@lang('Published')</option>
                                        <option value="archived" @selected(request('status') == 'archived')>@lang('Archived')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Difficulty')</label>
                                    <select name="difficulty" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        <option value="easy" @selected(request('difficulty') == 'easy')>@lang('Easy')</option>
                                        <option value="medium" @selected(request('difficulty') == 'medium')>@lang('Medium')</option>
                                        <option value="hard" @selected(request('difficulty') == 'hard')>@lang('Hard')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Quiz Type')</label>
                                    <select name="quiz_type" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        <option value="free" @selected(request('quiz_type') == 'free')>@lang('Free')</option>
                                        <option value="paid" @selected(request('quiz_type') == 'paid')>@lang('Paid')</option>
                                        <option value="subscription" @selected(request('quiz_type') == 'subscription')>@lang('Subscription')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn--primary w-100 h-45">
                                    <i class="las la-search"></i> @lang('Search')
                                </button>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="{{ route('admin.quiz.index') }}" class="btn btn--dark w-100 h-45">
                                    <i class="las la-undo"></i> @lang('Reset')
                                </a>
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
                                    <th>@lang('Title')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Sub-Category')</th>
                                    <th>@lang('Difficulty')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Questions')</th>
                                    <th>@lang('Time Limit')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quizzes as $quiz)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($quiz->image)
                                                    <div class="me-2">
                                                        <img src="{{ getImage(getFilePath('exam') . '/' . $quiz->image) }}" alt="" width="40" class="rounded">
                                                    </div>
                                                @endif
                                                <div>
                                                    <span class="fw-bold">{{ $quiz->title }}</span>
                                                    <small class="d-block text-muted">{{ $quiz->slug }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span>{{ __($quiz?->category?->name ?? '-') }}</span>
                                        </td>
                                        <td>
                                            <span>{{ __($quiz?->subCategory?->name ?? '-') }}</span>
                                        </td>
                                        <td>
                                            @php echo $quiz->difficultyBadge; @endphp
                                        </td>
                                        <td>
                                            @php echo $quiz->typeBadge; @endphp
                                        </td>
                                        <td>
                                            <span class="badge badge--info">{{ $quiz->questions_count }} / {{ $quiz->total_questions }}</span>
                                        </td>
                                        <td>
                                            <span>{{ $quiz->time_limit }} @lang('Min')</span>
                                        </td>
                                        <td>
                                            @php echo $quiz->quizStatusBadge; @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.quiz.show', $quiz->id) }}" class="btn btn--sm btn-outline--primary">
                                                    <i class="la la-eye"></i> @lang('Manage')
                                                </a>
                                                <button class="btn btn--sm btn-outline--info" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="las la-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{ route('admin.quiz.preview', $quiz->id) }}" target="_blank">
                                                        <i class="la la-play-circle"></i> @lang('Preview')
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('admin.quiz.create', $quiz->id) }}">
                                                        <i class="la la-pencil"></i> @lang('Edit')
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('admin.quiz.seo', $quiz->id) }}">
                                                        <i class="la la-search"></i> @lang('SEO')
                                                    </a>
                                                    @if($quiz->status == \App\Models\Quiz::STATUS_PUBLISHED)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.quiz.status', $quiz->id) }}" data-question="@lang('Are you sure to unpublish this quiz?')">
                                                            <i class="la la-eye-slash"></i> @lang('Unpublish')
                                                        </button>
                                                    @else
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.quiz.status', $quiz->id) }}" data-question="@lang('Are you sure to publish this quiz?')">
                                                            <i class="la la-eye"></i> @lang('Publish')
                                                        </button>
                                                    @endif
                                                    <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.quiz.delete', $quiz->id) }}" data-question="@lang('Are you sure to delete this quiz?')">
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
                @if ($quizzes->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($quizzes) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.quiz.create') }}">
        <i class="las la-plus"></i>@lang('Add New Quiz')
    </a>
@endpush
