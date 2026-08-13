@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Exams' => route('exams')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>Competitive Exam Preparation</h1>
                <p>Choose your target exam and practise topic by topic.</p>
            </div>
        </div>
        @if ($exams->count())
            <div class="row g-3">
                @foreach ($exams as $exam)
                    <div class="col-sm-6 col-lg-4">
                        <x-website::exam-card :exam="$exam"
                            :quizCount="$quizCounts[$exam->id] ?? 0"
                            :questionCount="$questionCounts[$exam->id] ?? 0" />
                    </div>
                @endforeach
            </div>
        @else
            <x-website::empty-state icon="bi-mortarboard" title="No exam categories configured" />
        @endif

        <div class="row g-3 mt-5">
            <div class="col-md-6">
                <a href="{{ route('website.mock.tests') }}" class="w-card text-decoration-none">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-stopwatch"></i> Mock Tests</h2>
                        <p class="w-muted mb-0">Full-length timed tests that mirror the real exam pattern.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="{{ route('website.pyq') }}" class="w-card text-decoration-none">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-archive"></i> Previous Year Questions</h2>
                        <p class="w-muted mb-0">Solved questions from past papers, with explanations.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
