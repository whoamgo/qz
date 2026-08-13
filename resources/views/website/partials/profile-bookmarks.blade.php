<h2 class="h5 mb-3">Saved quizzes</h2>
@if ($quizBookmarks->count())
    <div class="row g-3 mb-4">
        @foreach ($quizBookmarks as $bm)
            @continue(!$bm->quiz)
            <div class="col-sm-6 col-lg-4"><x-website::quiz-card :quiz="$bm->quiz" /></div>
        @endforeach
    </div>
    <x-website::pagination :paginator="$quizBookmarks" />
@else
    <div class="w-card mb-4"><x-website::empty-state icon="bi-bookmark"
        title="No saved quizzes"
        message="Use the bookmark button on any quiz to save it for later."
        :actionUrl="route('website.quizzes')" actionLabel="Browse quizzes" /></div>
@endif

<h2 class="h5 mb-3 mt-4">Saved questions</h2>
@if ($questionBookmarks->count())
    @foreach ($questionBookmarks as $bm)
        @continue(!$bm->question)
        <div class="w-card mb-3"><div class="w-card-body">
            <p class="fw-semibold mb-2">{{ $bm->question->question_text }}</p>
            <div class="w-meta mb-2">
                <span><i class="bi bi-folder2"></i> {{ $bm->question->category->name ?? '—' }}</span>
                <span class="w-badge w-badge-{{ $bm->question->difficulty }}">{{ ucfirst($bm->question->difficulty) }}</span>
            </div>
            @if ($bm->question->explanation)
                <div class="w-explanation"><strong>Explanation:</strong> {{ $bm->question->explanation }}</div>
            @endif
        </div></div>
    @endforeach
    <x-website::pagination :paginator="$questionBookmarks" />
@else
    <div class="w-card"><x-website::empty-state icon="bi-journal-bookmark"
        title="No saved questions"
        message="Save individual questions from the answer review screen after a quiz." /></div>
@endif
