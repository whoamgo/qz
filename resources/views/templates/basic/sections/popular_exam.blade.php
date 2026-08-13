@php
    $popularExamContent = getContent('popular_exam.content', true);
    $popularExams = App\Models\Exam::active()->popular()->with('category:id,name')->limit(6)->get();
@endphp

@if (!blank($popularExams))
    <section class="exam-section my-120">
        <div class="container">
            <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
                <h2 class="section-heading__title" data-highlight-start="{{ $popularExamContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $popularExamContent?->data_values?->highlight_end ?? 3 }}">
                    {{ __($popularExamContent?->data_values?->heading ?? 'Most Popular Quizzes') }}
                </h2>
                <p class="section-heading__desc">
                    {{ __($popularExamContent?->data_values?->short_description ?? 'Challenge yourself with our most taken quizzes by thousands of users.') }}
                </p>
            </div>
            <div class="row gy-4">
                @include('Template::partials.exam_card', ['exams' => $popularExams])
            </div>
        </div>
    </section>
@endif
