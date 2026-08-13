@extends('website.layouts.profile')

@section('profile-content')
<div class="w-stat-grid mb-4">
        @foreach ([
            ['Quizzes taken', $stats['attempts'], 'bi-list-check'],
            ['Questions answered', $stats['answered'], 'bi-question-circle'],
            ['Correct answers', $stats['correct'], 'bi-check-circle'],
            ['Accuracy', $stats['accuracy'] . '%', 'bi-bullseye'],
        ] as [$label, $value, $icon])
            <div class="w-stat-tile">
                <strong><i class="bi {{ $icon }}"></i> {{ $value }}</strong>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </div>

    @if ($inProgress->count())
        <h2 class="h5 mb-3">Continue where you left off</h2>
        <div class="row g-3 mb-4">
            @foreach ($inProgress as $a)
                <div class="col-md-4">
                    <div class="w-card"><div class="w-card-body">
                        <h3 class="w-card-title" style="font-size: var(--w-fs-base);">{{ $a->quiz->title }}</h3>
                        <a href="{{ route('website.quiz.attempt', $a->id) }}" class="btn w-btn-primary btn-sm mt-2">Resume</a>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="h5 mb-3">Recent results</h2>
    @if ($recent->count())
        <div class="w-card">
            <div class="w-table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Quiz</th><th>Score</th><th>Result</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($recent as $a)
                            <tr>
                                <td><a href="{{ route('website.quiz.show', $a->quiz->slug) }}">{{ $a->quiz->title }}</a></td>
                                <td>{{ $a->percentage }}%</td>
                                <td><span class="w-badge {{ $a->passed ? 'w-badge-free' : 'w-badge-hard' }}">{{ $a->passed ? 'Passed' : 'Failed' }}</span></td>
                                <td class="w-text-sm w-muted">{{ showDateTime($a->submitted_at, 'd M Y') }}</td>
                                <td><a href="{{ route('website.quiz.result', $a->id) }}" class="btn w-btn-outline btn-sm">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="w-card"><x-website::empty-state icon="bi-clipboard-data"
            title="No quizzes completed yet"
            message="Your results will appear here once you finish your first quiz."
            :actionUrl="route('website.quizzes')" actionLabel="Browse quizzes" /></div>
    @endif

    @if ($badges->count())
        <h2 class="h5 mb-3 mt-4">Recent badges</h2>
        <div class="row g-3">
            @foreach ($badges as $badge)
                <div class="col-6 col-md-4 col-lg-3"><x-website::badge-card :badge="$badge" :earned="true" /></div>
            @endforeach
        </div>
    @endif
@endsection
