@extends('admin.layouts.app')
@section('panel')

    <div class="alert alert--info"><i class="las la-info-circle"></i>
        Actionable opportunities derived from your real quiz inventory — no pages are created here.
        Work top-down: pages with the most quizzes but missing SEO content/keywords are the biggest wins.</div>

    {{-- Summary --}}
    <div class="row gy-4 mb-2">
        @foreach ([
            ['Missing SEO Content', $summary['missing_content'], 'las la-file-alt', 'warning'],
            ['Missing Primary Keyword', $summary['missing_keyword'], 'las la-key', 'warning'],
            ['Thin Pages', $summary['thin'], 'las la-compress-alt', 'danger'],
            ['Orphan Pages', $summary['orphans'], 'las la-unlink', 'danger'],
            ['Keyword Cannibalisation', $summary['dup_keywords'], 'las la-clone', 'dark'],
            ['Exam/Category Dual URLs', $summary['dual_urls'], 'las la-random', 'primary'],
        ] as [$label, $value, $icon, $color])
            <div class="col-xxl-2 col-lg-4 col-sm-6">
                <div class="card h-100"><div class="card-body d-flex align-items-center gap-2">
                    <span class="bg--{{ $color }} text-white rounded d-inline-flex align-items-center justify-content-center" style="width:46px;height:46px;font-size:20px;flex-shrink:0;"><i class="{{ $icon }}"></i></span>
                    <div><h4 class="mb-0">{{ number_format((int) $value) }}</h4><span class="text-muted small">@lang($label)</span></div>
                </div></div>
            </div>
        @endforeach
    </div>

    @php
        $taxTable = function ($rows, $emptyMsg) {
            return view('admin.seo._opp_table', ['rows' => $rows, 'emptyMsg' => $emptyMsg])->render();
        };
    @endphp

    <div class="card mt-3"><div class="card-header"><h6 class="mb-0">Missing SEO Content <span class="text-muted">— has quizzes, no SEO content (top 50 by quiz count)</span></h6></div>
        <div class="card-body p-0">{!! $taxTable($missingContent, 'Great — every page with quizzes has SEO content.') !!}</div></div>

    <div class="card mt-3"><div class="card-header"><h6 class="mb-0">Missing Primary Keyword <span class="text-muted">— has quizzes, no primary keyword mapped (top 50)</span></h6></div>
        <div class="card-body p-0">{!! $taxTable($missingKeyword, 'Every page with quizzes has a primary keyword.') !!}</div></div>

    <div class="card mt-3"><div class="card-header"><h6 class="mb-0">Orphan Pages <span class="text-muted">— active sub-topic with quizzes but its parent category is disabled (unreachable in nav)</span></h6></div>
        <div class="card-body p-0">{!! $taxTable($orphans, 'No orphaned sub-categories detected.') !!}</div></div>

    <div class="card mt-3"><div class="card-header"><h6 class="mb-0">Thin Pages <span class="text-muted">— active but no quizzes and no content (kept noindex)</span></h6></div>
        <div class="card-body p-0">{!! $taxTable($thin, 'No thin pages.') !!}</div></div>

    {{-- Keyword cannibalisation --}}
    <div class="card mt-3"><div class="card-header"><h6 class="mb-0">Keyword Cannibalisation <span class="text-muted">— same primary keyword on more than one page</span></h6></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table--light style--two table">
            <thead><tr><th>Primary Keyword</th><th class="text-end">Pages</th></tr></thead>
            <tbody>
                @forelse ($kwCannibal as $kw => $count)
                    <tr><td>{{ $kw }}</td><td class="text-end"><span class="badge badge--dark">{{ $count }}</span></td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted py-3">No keyword cannibalisation — each primary keyword is unique.</td></tr>
                @endforelse
            </tbody>
        </table></div></div></div>

    {{-- Exam/category dual URLs --}}
    <div class="card mt-3 mb-5"><div class="card-header"><h6 class="mb-0">Exam / Category Dual URLs <span class="text-muted">— same category at two URLs; set a canonical to consolidate</span></h6></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table--light style--two table">
            <thead><tr><th>Category</th><th>Category URL</th><th>Exam URL</th><th>Canonical set?</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($dualUrls as $d)
                    <tr>
                        <td class="fw-bold">{{ $d->name }}</td>
                        <td><a href="{{ $d->category_url }}" target="_blank" class="text-truncate d-inline-block" style="max-width:220px;">{{ $d->category_url }}</a></td>
                        <td><a href="{{ $d->exam_url }}" target="_blank" class="text-truncate d-inline-block" style="max-width:220px;">{{ $d->exam_url }}</a></td>
                        <td>@if ($d->has_canonical)<span class="badge badge--success">Yes</span>@else<span class="badge badge--warning">No</span>@endif</td>
                        <td><a href="{{ $d->edit }}" class="btn btn-sm btn-outline--primary"><i class="las la-pencil"></i> Set canonical</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No dual-URL categories.</td></tr>
                @endforelse
            </tbody>
        </table></div></div></div>
@endsection
