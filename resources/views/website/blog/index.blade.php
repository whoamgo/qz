@extends('website.layouts.app')
@push('styles')<link href="{{ wAsset('assets/web/css/blog.css') }}" rel="stylesheet">@endpush
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Blog' => route('blog')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>Blog</h1>
                <p>Preparation strategy, study guides and exam tips.</p>
            </div>
            <form action="{{ route('blog') }}" method="GET" role="search" class="position-relative" style="max-width: 280px;">
                <i class="bi bi-search w-search-icon" aria-hidden="true"></i>
                <input type="search" name="q" class="form-control form-control-sm w-search-input"
                       value="{{ request('q') }}" placeholder="Search articles..." aria-label="Search articles">
            </form>
        </div>

        @if ($featured)
            @php
                $fv = $featured->data_values;
                $fbody = strip_tags($fv->description ?? '');
            @endphp
            <article class="w-blog-featured mb-5">
                <img src="{{ frontendImage('blog', 'thumb_' . ($fv->image ?? ''), '420x280') }}"
                     alt="{{ $fv->title ?? '' }}" loading="eager" width="420" height="280">
                <div class="w-blog-featured-body">
                    <span class="w-badge w-badge-primary mb-2">Featured</span>
                    <h2 class="mb-3"><a href="{{ route('blog.details', $featured->slug) }}" class="text-decoration-none text-dark">{{ $fv->title ?? 'Untitled' }}</a></h2>
                    <div class="w-blog-meta mb-3">
                        <span><i class="bi bi-calendar3"></i> {{ showDateTime($featured->created_at, 'd M Y') }}</span>
                        <span><i class="bi bi-clock"></i> {{ max(1, (int) ceil(str_word_count($fbody) / 200)) }} min read</span>
                    </div>
                    <p class="w-muted">{{ \Illuminate\Support\Str::limit($fbody, 200) }}</p>
                    <a href="{{ route('blog.details', $featured->slug) }}" class="btn w-btn-primary mt-2 align-self-start">Read article</a>
                </div>
            </article>
        @endif

        @if ($blogs->count())
            <div class="row g-4">
                @foreach ($blogs as $blog)
                    <div class="col-sm-6 col-lg-4"><x-website::blog-card :blog="$blog" /></div>
                @endforeach
            </div>
            <x-website::pagination :paginator="$blogs" />
        @elseif (!$featured)
            <div class="w-card"><x-website::empty-state icon="bi-journal-text"
                title="No articles published yet"
                message="Articles added from the admin Frontend Manager will appear here." /></div>
        @endif
    </div>
</section>
@endsection
