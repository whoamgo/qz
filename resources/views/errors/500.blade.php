@php
    // Rendered inside the site chrome so it matches the rest of the website.
    // Note: if a 500 is ever caused by the database being unreachable, the site
    // layout (header/footer read cached data) can't render and Laravel falls
    // back to its own minimal error page — which is the safe, expected behaviour.
    $seo = ['title' => 'Something went wrong', 'robots' => 'noindex, follow'];
@endphp
@extends('website.layouts.app')

@push('styles')
    @include('errors.partials.style')
@endpush

@section('content')
<section class="w-section w-err">
    <div class="container">
        <div class="w-err-inner">
            <div class="w-err-code" aria-hidden="true">500</div>
            <h1 class="mb-2">Something went wrong</h1>
            <p class="w-muted mb-4">
                Sorry, an unexpected error occurred on our end. Our team has been notified —
                please try again in a moment.
            </p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mb-4">
                <button type="button" class="btn w-btn-primary btn-lg" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Try again
                </button>
                <a href="{{ route('home') }}" class="btn w-btn-outline btn-lg">
                    <i class="bi bi-house-door" aria-hidden="true"></i> Go to Home
                </a>
            </div>

            <p class="w-text-sm w-muted mb-2">Popular pages</p>
            <div class="w-err-links">
                <a href="{{ route('website.quizzes') }}"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Quizzes</a>
                <a href="{{ route('website.categories') }}"><i class="bi bi-folder2" aria-hidden="true"></i> Categories</a>
                <a href="{{ route('website.current.affairs.index') }}"><i class="bi bi-newspaper" aria-hidden="true"></i> Current Affairs</a>
                <a href="{{ route('website.leaderboard') }}"><i class="bi bi-trophy" aria-hidden="true"></i> Leaderboard</a>
                <a href="{{ route('website.faq') }}"><i class="bi bi-question-circle" aria-hidden="true"></i> FAQ</a>
            </div>
        </div>
    </div>
</section>
@endsection
