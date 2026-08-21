@extends('admin.layouts.app')
@section('panel')

    {{-- Type tabs + search + missing filter --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2">
                @foreach (['category' => 'Categories', 'subcategory' => 'Sub-categories', 'quiz' => 'Quizzes', 'blog' => 'Blog'] as $k => $label)
                    <a href="{{ route('admin.seo.manager.bulk', ['type' => $k, 'missing' => $missing ? 1 : null]) }}"
                       class="btn btn-sm {{ $type === $k ? 'btn--primary' : 'btn-outline--primary' }}">{{ __($label) }}</a>
                @endforeach
            </div>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="form-check mb-0">
                    <input type="checkbox" class="form-check-input" id="missing" name="missing" value="1" @checked($missing) onchange="this.form.submit()">
                    <label class="form-check-label" for="missing">@lang('Only missing SEO')</label>
                </div>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="@lang('Search…')">
                <button class="btn btn-sm btn--primary"><i class="las la-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">@lang('Bulk SEO')<span class="text-muted"> — {{ ucfirst($type) }}</span></h5>
            @if (in_array($type, ['category', 'subcategory']))
                <form action="{{ route('admin.seo.manager.generate') }}" method="POST" onsubmit="return confirm('Fill only BLANK SEO fields for all categories? Existing values are never changed.')">
                    @csrf<input type="hidden" name="type" value="category">
                    <button class="btn btn-sm btn--success"><i class="las la-magic"></i> @lang('Generate missing')</button>
                </form>
            @elseif ($type === 'quiz')
                <form action="{{ route('admin.seo.manager.generate') }}" method="POST" onsubmit="return confirm('Fill only BLANK SEO fields for all quizzes? Existing values are never changed.')">
                    @csrf<input type="hidden" name="type" value="quiz">
                    <button class="btn btn-sm btn--success"><i class="las la-magic"></i> @lang('Generate missing')</button>
                </form>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table--light style--two table">
                <thead>
                    <tr>
                        <th>@lang('Name')</th>
                        <th>@lang('SEO Title')</th>
                        <th>@lang('Meta Description')</th>
                        <th>@lang('Score')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            if ($type === 'quiz') {
                                $editUrl = route('admin.quiz.seo', $row->id);
                                $name = $row->title; $mt = $row->meta_title; $md = $row->meta_description; $score = $row->seo_score;
                            } elseif ($type === 'blog') {
                                $editUrl = route('admin.frontend.sections.element.seo', ['blog', $row->id]);
                                $name = $row->data_values->title ?? $row->slug; $mt = null; $md = $row->seo_content->description ?? null; $score = null;
                            } else {
                                $editUrl = route('admin.category.seo', $row->id);
                                $name = ($type === 'subcategory' && $row->parent) ? $row->parent->name . ' → ' . $row->name : $row->name;
                                $mt = $row->meta_title; $md = $row->meta_description; $score = $row->seo_score;
                            }
                        @endphp
                        <tr>
                            <td><span class="fw-bold">{{ $name }}</span></td>
                            <td class="text-truncate" style="max-width:260px;">
                                @if ($type === 'blog')
                                    <span class="text-muted">@lang('uses post title')</span>
                                @elseif (filled($mt))
                                    {{ $mt }}
                                @else
                                    <span class="badge badge--warning">@lang('auto')</span>
                                @endif
                            </td>
                            <td class="text-truncate" style="max-width:300px;">
                                @if (filled($md)) {{ $md }} @else <span class="badge badge--warning">@lang('auto')</span> @endif
                            </td>
                            <td>
                                @if (!is_null($score))
                                    <span class="badge badge--{{ $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger') }}">{{ $score }}</span>
                                @else <span class="text-muted">—</span> @endif
                            </td>
                            <td><a href="{{ $editUrl }}" class="btn btn-sm btn-outline--primary"><i class="las la-pencil"></i> @lang('Edit SEO')</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">@lang('No records found.')</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer py-4">{{ paginateLinks($rows) }}</div>
        @endif
    </div>
@endsection
