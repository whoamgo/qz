@extends('admin.layouts.app')
@section('panel')

    {{-- ---- Coverage cards ---- --}}
    <div class="row gy-4 mb-2">
        @foreach ([
            ['Indexable Pages', $cards['indexable'], 'las la-globe', 'success'],
            ['Categories', $cards['parents'], 'las la-list', 'primary'],
            ['Sub-categories', $cards['subs'], 'las la-sitemap', 'primary'],
            ['Quizzes', $cards['quizzes'], 'las la-question-circle', 'info'],
            ['Blog Posts', $cards['blogs'], 'las la-newspaper', 'info'],
            ['Using Auto Title', $cards['auto_title'], 'las la-magic', 'warning'],
            ['Using Auto Description', $cards['auto_desc'], 'las la-magic', 'warning'],
            ['Duplicate Titles', $cards['dup_titles'], 'las la-copy', 'danger'],
            ['Duplicate Descriptions', $cards['dup_descs'], 'las la-copy', 'danger'],
            ['Noindex Pages', $cards['noindex'], 'las la-eye-slash', 'dark'],
        ] as [$label, $value, $icon, $color])
            <div class="col-xxl-3 col-lg-4 col-sm-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="bg--{{ $color }} text-white rounded d-inline-flex align-items-center justify-content-center"
                              style="width:52px;height:52px;font-size:24px;flex-shrink:0;"><i class="{{ $icon }}"></i></span>
                        <div class="overflow-hidden">
                            <h4 class="mb-0">{{ number_format((int) $value) }}</h4>
                            <span class="text-muted">@lang($label)</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert alert--info mt-2"><i class="las la-info-circle"></i>
        “Using Auto” means the page currently relies on the auto-generated SEO fallback (perfectly valid — every page always has a title, description and H1). It's simply a candidate for manual optimisation. Values are advisory, not Google's score.</div>

    {{-- ---- Quick actions ---- --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">@lang('Quick Actions')</h5></div>
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.seo.manager.bulk', ['type' => 'category']) }}" class="btn btn-outline--primary btn-sm">@lang('Categories')</a>
            <a href="{{ route('admin.seo.manager.bulk', ['type' => 'subcategory']) }}" class="btn btn-outline--primary btn-sm">@lang('Sub-categories')</a>
            <a href="{{ route('admin.seo.manager.bulk', ['type' => 'quiz']) }}" class="btn btn-outline--primary btn-sm">@lang('Quizzes')</a>
            <a href="{{ route('admin.seo.manager.bulk', ['type' => 'blog']) }}" class="btn btn-outline--primary btn-sm">@lang('Blog')</a>
            <span class="ms-auto"></span>
            <form action="{{ route('admin.seo.manager.generate') }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Fill only the BLANK SEO fields for all categories? Existing values are never changed.')">
                @csrf<input type="hidden" name="type" value="category">
                <button class="btn btn--success btn-sm"><i class="las la-magic"></i> @lang('Generate missing (Categories)')</button>
            </form>
            <form action="{{ route('admin.seo.manager.generate') }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Fill only the BLANK SEO fields for all quizzes? Existing values are never changed.')">
                @csrf<input type="hidden" name="type" value="quiz">
                <button class="btn btn--success btn-sm"><i class="las la-magic"></i> @lang('Generate missing (Quizzes)')</button>
            </form>
        </div>
    </div>

    {{-- ---- Duplicates ---- --}}
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('Duplicate SEO Titles')</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive"><table class="table--light style--two table">
                        <thead><tr><th>@lang('Title')</th><th class="text-end">@lang('Count')</th></tr></thead>
                        <tbody>
                            @forelse ($dupTitles as $val => $count)
                                <tr><td class="text-truncate" style="max-width:360px;">{{ $val }}</td><td class="text-end"><span class="badge badge--danger">{{ $count }}</span></td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">@lang('No duplicate titles found.')</td></tr>
                            @endforelse
                        </tbody>
                    </table></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('Duplicate Meta Descriptions')</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive"><table class="table--light style--two table">
                        <thead><tr><th>@lang('Description')</th><th class="text-end">@lang('Count')</th></tr></thead>
                        <tbody>
                            @forelse ($dupDescs as $val => $count)
                                <tr><td class="text-truncate" style="max-width:360px;">{{ $val }}</td><td class="text-end"><span class="badge badge--danger">{{ $count }}</span></td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">@lang('No duplicate descriptions found.')</td></tr>
                            @endforelse
                        </tbody>
                    </table></div>
                </div>
            </div>
        </div>
    </div>
@endsection
