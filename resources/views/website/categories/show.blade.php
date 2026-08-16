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
                            @if ($subCategories->count()){{ $subCategories->count() }} topics &middot; @endif
                            {{ number_format($questionTotal) }} practice questions
                        </p>
                    </div>
                </div>

                {{-- Intro copy: admin-entered description if present, otherwise a
                     natural, useful summary. Adds real content for SEO. --}}
                <div class="w-article-body w-muted">
                    @if (!empty($category->meta_description))
                        <p class="mb-0">{{ $category->meta_description }}</p>
                    @else
                        <p class="mb-0">
                            Practise <strong>{{ $category->name }}</strong> with free online quizzes and mock tests on Quiz Mitra.
                            @if ($subCategories->count())
                                This section is organised into {{ $subCategories->count() }} topics, so you can focus on exactly what you need to revise.
                            @endif
                            Every question comes with a written explanation, so you learn the reasoning rather than memorising an answer key.
                            Attempt any quiz to get an instant score, review your mistakes, earn XP and climb the leaderboard — a simple, free way to
                            prepare for {{ $category->name }} sections in competitive exams and general-knowledge tests.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Topics appear only when this category actually has sub-categories,
             so a flat category never shows an empty navigation level. --}}
        @if ($subCategories->count())
            <div class="w-section-head"><div><h2>Browse by topic</h2></div></div>
            <div class="row g-3 mb-5">
                @foreach ($subCategories as $sub)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-website::category-card :category="$sub"
                            :quizCount="$subQuizCounts[$sub->id] ?? 0"
                            :url="route('website.subcategory.show', [$category->slug, $sub->slug])" />
                    </div>
                @endforeach
            </div>
        @endif

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
