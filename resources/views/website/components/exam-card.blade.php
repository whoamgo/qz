@props(['exam', 'quizCount' => 0, 'questionCount' => 0])
<a href="{{ route('website.exam.show', $exam->slug) }}" class="w-card text-decoration-none">
    <div class="w-card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="w-cat-icon m-0" style="width:48px;height:48px;font-size:1.25rem;" aria-hidden="true">
                @include('website.partials.category-icon', ['category' => $exam, 'fallback' => 'bi-mortarboard'])
            </span>
            <div>
                <h3 class="w-card-title mb-0" style="font-size: var(--w-fs-lg);">{{ $exam->name }}</h3>
                <small class="w-muted">{{ $exam->sub_count ?? 0 }} subjects</small>
            </div>
        </div>
        <div class="w-meta">
            <span><i class="bi bi-patch-question" aria-hidden="true"></i> {{ $quizCount }} quizzes</span>
            <span><i class="bi bi-list-ol" aria-hidden="true"></i> {{ number_format($questionCount) }} questions</span>
        </div>
    </div>
    <div class="w-card-footer w-text-sm text-primary fw-semibold">
        Start preparing <i class="bi bi-arrow-right" aria-hidden="true"></i>
    </div>
</a>
