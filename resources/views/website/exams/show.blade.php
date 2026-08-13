@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => route('home'), 'Exams' => route('exams'),
        $exam->name => route('website.exam.show', $exam->slug)]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-card mb-4">
            <div class="w-card-body">
                <h1 class="mb-2">{{ $exam->name }} Preparation</h1>
                <p class="w-muted mb-3">
                    {{ number_format($questionTotal) }} practice questions
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('website.quizzes') }}?category={{ $exam->slug }}" class="btn w-btn-primary btn-sm">
                        <i class="bi bi-play-fill"></i> Practise now
                    </a>
                    <a href="{{ route('website.mock.tests') }}" class="btn w-btn-outline btn-sm">Mock tests</a>
                    <a href="{{ route('website.pyq') }}" class="btn w-btn-outline btn-sm">Previous year questions</a>
                </div>
            </div>
        </div>

        @if ($popularQuizzes->count())
            <div class="w-section-head"><div><h2>Popular {{ $exam->name }} Quizzes</h2></div></div>
            <div class="row g-3 mb-5">
                @foreach ($popularQuizzes as $quiz)
                    <div class="col-sm-6 col-lg-4"><x-website::quiz-card :quiz="$quiz" /></div>
                @endforeach
            </div>
        @endif

        {{-- Mock tests and previous-year papers now link to their hub pages
             rather than to sub-category listings, keeping the public path
             Category -> Quiz. --}}
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <a href="{{ route('website.mock.tests') }}" class="w-card text-decoration-none">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-stopwatch"></i> Mock Tests</h2>
                        <p class="w-text-sm w-muted mb-0">Full-length timed practice tests.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="{{ route('website.pyq') }}" class="w-card text-decoration-none">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-archive"></i> Previous Year Questions</h2>
                        <p class="w-text-sm w-muted mb-0">Solved questions from past papers.</p>
                    </div>
                </a>
            </div>
        </div>

        @if ($currentAffairs->count())
            <div class="w-section-head"><div><h2>Current Affairs for {{ $exam->name }}</h2></div></div>
            <div class="row g-3 mb-5">
                @foreach ($currentAffairs as $quiz)
                    <div class="col-sm-6 col-lg-3"><x-website::quiz-card :quiz="$quiz" /></div>
                @endforeach
            </div>
        @endif

        <x-website::faq-accordion :faqs="$faqs" id="wExamFaq" />
    </div>
</section>
@endsection
