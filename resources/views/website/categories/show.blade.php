@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => route('home'), 'Categories' => route('website.categories'),
        $category->name => route('website.category.show', $category->slug)]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-card mb-4">
            <div class="w-card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="w-cat-icon m-0" style="width:56px;height:56px;">
                        @include('website.partials.category-icon', ['category' => $category])
                    </span>
                    <div>
                        <h1 class="mb-1">{{ $category->name }} Quizzes</h1>
                        <p class="w-muted mb-0">
                            {{ number_format($questionTotal) }} practice questions
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @if ($popularQuizzes->count())
            <div class="w-section-head"><div><h2>Popular in {{ $category->name }}</h2></div></div>
            <div class="row g-3 mb-5">
                @foreach ($popularQuizzes as $quiz)
                    <div class="col-sm-6 col-lg-4"><x-website::quiz-card :quiz="$quiz" /></div>
                @endforeach
            </div>
        @endif

        <div class="w-section-head"><div><h2>All {{ $category->name }} Quizzes</h2></div></div>
        @include('website.partials.quiz-grid', ['quizzes' => $latestQuizzes])

        <div class="mt-5">
            <x-website::faq-accordion :faqs="$faqs" id="wCatFaq" />
        </div>
    </div>
</section>
@endsection
