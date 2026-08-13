@php
    $latestExamContent = getContent('latest_exam.content', true);
    $upcomingExams = App\Models\Exam::active()->upcoming()->orderBy('start_at', 'asc')->with('category:id,name')->limit(3)->get();
@endphp

@if (!blank($upcomingExams))
    <section class="exam-section my-120">
        <div class="container">
            <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
                <h2 class="section-heading__title" data-highlight-start="{{ $latestExamContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $latestExamContent?->data_values?->highlight_end ?? 3 }}">
                    {{ __($latestExamContent?->data_values?->heading ?? '') }}
                </h2>
                <p class="section-heading__desc">
                    {{ __($latestExamContent?->data_values?->short_description ?? '') }}
                </p>
            </div>
            <div class="row gy-4">
                @include('Template::partials.exam_card', ['exams' => $upcomingExams])
            </div>
        </div>
    </section>
@endif
