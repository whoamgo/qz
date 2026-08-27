@extends('website.layouts.app')

@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Quizzes' => route('website.quizzes')]" />
@endsection

@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>All Quizzes</h1>
                <p>{{ number_format($quizzes->total()) }} published {{ \Illuminate\Support\Str::plural('quiz', $quizzes->total()) }} across every category.</p>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            {{-- Filters --}}
            <div class="col-lg-3">
                <form class="wFilterForm w-card" id="wQuizFilters" action="{{ route('website.quizzes') }}" method="GET">
                    <div class="w-card-body">
                        <h2 class="w-card-title" style="font-size: var(--w-fs-base);">
                            <i class="bi bi-funnel" aria-hidden="true"></i> Filters
                        </h2>

                        <div class="mb-3">
                            <label class="form-label w-text-sm" for="fSearch">Search</label>
                            <input type="search" id="fSearch" name="q" class="form-control form-control-sm"
                                   value="{{ request('q') }}" placeholder="Quiz title...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label w-text-sm" for="fCategory">Category</label>
                            <select id="fCategory" name="category" class="form-select form-select-sm">
                                <option value="">All categories</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label w-text-sm" for="fDifficulty">Difficulty</label>
                            <select id="fDifficulty" name="difficulty" class="form-select form-select-sm">
                                <option value="">Any difficulty</option>
                                @foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $v => $l)
                                    <option value="{{ $v }}" @selected(request('difficulty') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label w-text-sm" for="fType">Quiz type</label>
                            <select id="fType" name="type" class="form-select form-select-sm">
                                <option value="">Any type</option>
                                @foreach (['free' => 'Free', 'paid' => 'Paid', 'subscription' => 'Subscription'] as $v => $l)
                                    <option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label w-text-sm" for="fSort">Sort by</label>
                            <select id="fSort" name="sort" class="form-select form-select-sm">
                                @foreach (['latest' => 'Newest first', 'oldest' => 'Oldest first', 'popular' => 'Most attempted', 'questions' => 'Most questions', 'title' => 'Title A–Z'] as $v => $l)
                                    <option value="{{ $v }}" @selected(request('sort', 'latest') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn w-btn-primary btn-sm">Apply filters</button>
                            <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline btn-sm">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Results --}}
            <div class="col-lg-9">
                @if ($quizzes->count())
                    <div class="row g-3">
                        @foreach ($quizzes as $quiz)
                            <div class="col-sm-6 col-xl-4"><x-website::quiz-card :quiz="$quiz" /></div>
                        @endforeach
                    </div>
                    <x-website::pagination :paginator="$quizzes" />
                @else
                    <div class="w-card">
                        <x-website::empty-state icon="bi-search"
                            title="No quizzes match those filters"
                            message="Try removing a filter or searching for a different topic."
                            :actionUrl="route('website.quizzes')" actionLabel="Clear filters" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
