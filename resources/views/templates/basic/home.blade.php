@extends('Template::layouts.frontend')
@section('content')

    @include('Template::sections.banner')

    @if (isset($sections->secs) && $sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif

    @if (!blank($popularExams) && (!isset($sections->secs) || !str_contains($sections->secs, 'popular_exam')))
        <section class="exam-section my-120">
            <div class="container">
                <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
                    <h2 class="section-heading__title" data-highlight-start="2" data-highlight-word="3">
                        @lang('Most Popular Quizzes')
                    </h2>
                    <p class="section-heading__desc">
                        @lang('Challenge yourself with our most taken quizzes by thousands of users.')
                    </p>
                </div>
                <div class="row gy-4">
                    @include('Template::partials.exam_card', ['exams' => $popularExams])
                </div>
            </div>
        </section>
    @endif

@endsection
