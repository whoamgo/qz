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
                <p class="w-muted mb-0">
                    Part of <a href="{{ route('website.category.show', $category->slug) }}">{{ $category->name }}</a>
                    &middot; {{ number_format($questionTotal) }} practice questions
                </p>
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
