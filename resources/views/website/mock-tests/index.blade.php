@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), $label => route('website.mock.tests')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>Mock Tests</h1>
                <p>Full-length practice tests with timing and instant scoring.</p>
            </div>
        </div>

        @include('website.partials.quiz-grid', [
            'quizzes' => $quizzes, 'emptyIcon' => 'bi-stopwatch',
            'emptyTitle' => 'No mock tests published yet',
        ])
    </div>
</section>
@endsection
