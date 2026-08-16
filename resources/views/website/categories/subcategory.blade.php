@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => route('home'), 'Categories' => route('website.categories'),
        $category->name => route('website.category.show', $category->slug),
        $sub->name => route('website.subcategory.show', [$category->slug, $sub->slug])]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-card mb-4">
            <div class="w-card-body">
                <h1 class="mb-2">{{ $sub->name }}</h1>
                <p class="w-muted mb-2">
                    Part of <a href="{{ route('website.category.show', $category->slug) }}">{{ $category->name }}</a>
                    &middot; {{ number_format($questionTotal) }} practice questions
                </p>
                <div class="w-article-body w-muted">
                    @if (!empty($sub->meta_description))
                        <p class="mb-0">{{ $sub->meta_description }}</p>
                    @else
                        <p class="mb-0">
                            Sharpen your <strong>{{ $sub->name }}</strong> knowledge with focused practice from the {{ $category->name }} section.
                            Each quiz is scored instantly and every question includes a written explanation, so you can spot weak areas and fix them fast.
                            Attempt the quizzes below to earn XP, track your accuracy and prepare confidently for {{ $sub->name }} questions in your exam.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        @include('website.partials.quiz-grid', [
            'quizzes' => $quizzes,
            'emptyTitle' => 'No quizzes for this topic yet',
            'emptyMessage' => 'There are ' . number_format($questionTotal) . ' questions in the bank for this topic, but no quiz has been published from them yet.',
        ])
    </div>
</section>
@endsection
