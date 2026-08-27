@extends('website.layouts.app')
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>{{ $term ? 'Results for "' . $term . '"' : 'Search' }}</h1>
                @if ($term)
                    <p>{{ $quizzes instanceof \Illuminate\Pagination\LengthAwarePaginator ? number_format($quizzes->total()) : 0 }} quizzes and {{ $categories->count() }} categories matched.</p>
                @endif
            </div>
        </div>

        <form action="{{ route('website.search') }}" method="GET" role="search" class="position-relative mb-5" style="max-width: 560px;">
            <i class="bi bi-search w-search-icon" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control form-control-lg w-search-input"
                   value="{{ $term }}" placeholder="Search quizzes, topics, exams..." aria-label="Search">
        </form>

        @if ($categories->count())
            <h2 class="h5 mb-3">Matching categories</h2>
            <div class="d-flex flex-wrap gap-2 mb-5">
                @foreach ($categories as $cat)
                    {{-- Sub-categories resolve to their parent: the public IA is
                         Category -> Quiz, with no sub-category level. --}}
                    <a class="w-badge" href="{{ route('website.category.show', $cat->parent_id && $cat->parent ? $cat->parent->slug : $cat->slug) }}">
                        {{ $cat->parent_id && $cat->parent ? $cat->parent->name : $cat->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($term)
            <h2 class="h5 mb-3">Matching quizzes</h2>
            @include('website.partials.quiz-grid', [
                'quizzes' => $quizzes, 'emptyIcon' => 'bi-search',
                'emptyTitle' => 'Nothing matched that search',
                'emptyMessage' => 'Try a shorter phrase, or browse by category instead.',
            ])
        @else
            <x-website::empty-state icon="bi-search" title="Start typing to search"
                message="Search across every published quiz, category and exam topic." />
        @endif
    </div>
</section>
@endsection
