@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), $label => route('website.pyq')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>Previous Year Questions</h1>
                <p>Solved questions from past examination papers, with explanations.</p>
            </div>
        </div>

        @include('website.partials.quiz-grid', [
            'quizzes' => $quizzes, 'emptyIcon' => 'bi-archive',
            'emptyTitle' => 'No previous year questions published yet',
        ])
    </div>
</section>
@endsection
