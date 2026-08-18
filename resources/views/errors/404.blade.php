@php
    // Render the 404 inside the site chrome so it matches the rest of the site.
    // noindex so the error page itself is never indexed. Header/footer define
    // their own data, so this works without a controller.
    $seo = ['title' => 'Page not found', 'robots' => 'noindex, follow'];
@endphp
@extends('website.layouts.app')

@push('styles')
    @include('errors.partials.style')
@endpush

@section('content')
<section class="w-section w-err">
    <div class="container">
        <div class="w-err-inner">
            <div class="w-err-code" aria-hidden="true">404</div>
            <h1 class="mb-2">Page not found</h1>
            <p class="w-muted mb-4">
                The page you’re looking for doesn’t exist, may have been moved, or is temporarily unavailable.
            </p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mb-4">
                <a href="{{ route('home') }}" class="btn w-btn-primary btn-lg">
                    <i class="bi bi-house-door" aria-hidden="true"></i> Go to Home
                </a>
                <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline btn-lg">
                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Browse Quizzes
                </a>
            </div>

            <form action="{{ route('website.search') }}" method="GET" role="search" class="w-err-search">
                <input type="search" name="q" class="form-control" placeholder="Search quizzes…" aria-label="Search quizzes">
                <button type="submit" class="btn w-btn-primary" aria-label="Search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </button>
            </form>

            <p class="w-text-sm w-muted mb-2">Popular pages</p>
            <div class="w-err-links">
                <a href="{{ route('website.categories') }}"><i class="bi bi-folder2" aria-hidden="true"></i> Categories</a>
                <a href="{{ route('website.current.affairs.index') }}"><i class="bi bi-newspaper" aria-hidden="true"></i> Current Affairs</a>
                <a href="{{ route('website.leaderboard') }}"><i class="bi bi-trophy" aria-hidden="true"></i> Leaderboard</a>
                <a href="{{ route('blog') }}"><i class="bi bi-journal-text" aria-hidden="true"></i> Blog</a>
                <a href="{{ route('website.faq') }}"><i class="bi bi-question-circle" aria-hidden="true"></i> FAQ</a>
            </div>
        </div>
    </div>
</section>
@endsection
