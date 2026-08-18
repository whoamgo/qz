@props(['quiz', 'questions' => null])
@php $questions = $questions ?? collect(); @endphp

@if ($questions->count())
    {{-- Scoped styles are emitted once even if the component is used twice on a
         page. All colours come from the site's existing CSS custom properties. --}}
    @once
        @push('styles')
            <style>
                .w-sq-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:var(--w-space-4);}
                .w-sq-item{border:1px solid var(--w-border);border-radius:var(--w-radius-lg);padding:var(--w-space-5);background:#fff;}
                .w-sq-num{font-size:var(--w-fs-sm);font-weight:700;color:var(--w-primary);text-transform:uppercase;letter-spacing:.06em;}
                .w-sq-q{font-size:var(--w-fs-lg);font-weight:600;line-height:1.55;margin:var(--w-space-2) 0 var(--w-space-4);}
                .w-sq-options{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:var(--w-space-2);}
                .w-sq-option{display:flex;align-items:flex-start;gap:var(--w-space-3);padding:.6rem .85rem;border:1px solid var(--w-border);border-radius:var(--w-radius);}
                .w-sq-option.is-correct{border-color:var(--w-success);background:var(--w-success-light);}
                .w-sq-key{flex-shrink:0;width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:var(--w-bg-alt);font-weight:700;font-size:var(--w-fs-sm);}
                .w-sq-option.is-correct .w-sq-key{background:var(--w-success);color:#fff;}
                .w-sq-answer-tag{margin-left:auto;flex-shrink:0;white-space:nowrap;}
                .w-sq-explanation{margin-top:var(--w-space-4);padding:var(--w-space-3) var(--w-space-4);border-left:3px solid var(--w-primary);background:var(--w-bg-alt);border-radius:var(--w-radius-sm);}
                .w-sq-cta{margin-top:var(--w-space-5);padding:var(--w-space-5);border:1px dashed var(--w-border);border-radius:var(--w-radius-lg);background:var(--w-bg-alt);text-align:center;}
            </style>
        @endpush
    @endonce

    <section class="w-sq" aria-labelledby="wSampleQuestionsHeading">
        <div class="w-section-head">
            <div>
                <h2 id="wSampleQuestionsHeading">Sample Questions &amp; Answers</h2>
                <p class="w-muted mb-0">
                    A preview of {{ $questions->count() }} question{{ $questions->count() === 1 ? '' : 's' }} from this quiz, each with the correct answer and a written explanation. Attempt the full, timed quiz to be scored and earn XP.
                </p>
            </div>
        </div>

        <ol class="w-sq-list">
            @foreach ($questions as $q)
                <li>
                    @php
                        // Single source of truth for the correct option: the
                        // is_correct flag (what the grader uses), falling back to
                        // correct_option_id only if nothing is flagged. Marks
                        // exactly one option so the badge is never ambiguous.
                        $correctId = optional($q->options->firstWhere('is_correct', true))->id ?? $q->correct_option_id;
                    @endphp
                    <article class="w-sq-item">
                        <span class="w-sq-num">Question {{ $loop->iteration }}</span>
                        <h3 class="w-sq-q">{{ $q->question_text }}</h3>

                        <ul class="w-sq-options">
                            @foreach ($q->options as $option)
                                @php $isCorrect = $option->id === $correctId; @endphp
                                <li class="w-sq-option {{ $isCorrect ? 'is-correct' : '' }}">
                                    <span class="w-sq-key" aria-hidden="true">{{ chr(65 + $loop->index) }}</span>
                                    <span class="flex-grow-1">{{ $option->option_text }}</span>
                                    @if ($isCorrect)
                                        <span class="w-badge w-badge-free w-sq-answer-tag">
                                            <i class="bi bi-check-lg" aria-hidden="true"></i> Correct answer
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if (filled($q->explanation))
                            <div class="w-sq-explanation">
                                <strong><i class="bi bi-lightbulb" aria-hidden="true"></i> Explanation:</strong>
                                <span class="w-muted">{{ $q->explanation }}</span>
                            </div>
                        @endif
                    </article>
                </li>
            @endforeach
        </ol>

        <div class="w-sq-cta">
            @auth
                <p class="mb-3">
                    <strong>Ready for the real thing?</strong>
                    Attempt the full, timed quiz to get an instant score, review every answer and earn XP.
                </p>
                <form action="{{ route('website.quiz.start', $quiz->slug) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn w-btn-primary btn-lg">
                        <i class="bi bi-play-fill" aria-hidden="true"></i> Start the full quiz
                    </button>
                </form>
            @else
                <p class="mb-3"><strong>Login/Register to attempt the full quiz and earn XP.</strong></p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="{{ route('user.login') }}" class="btn w-btn-primary btn-lg">Login</a>
                    <a href="{{ route('user.register') }}" class="btn w-btn-outline btn-lg">Create free account</a>
                </div>
            @endauth

            @if ($quiz->category)
                <p class="w-muted mt-3 mb-0">
                    Explore more:
                    <a href="{{ route('website.category.show', $quiz->category->slug) }}">All {{ $quiz->category->name }} quizzes</a>
                    @if ($quiz->subCategory)
                        &middot; <a href="{{ route('website.subcategory.show', [$quiz->category->slug, $quiz->subCategory->slug]) }}">{{ $quiz->subCategory->name }}</a>
                    @endif
                </p>
            @endif
        </div>
    </section>
@endif
