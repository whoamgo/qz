@extends('website.layouts.profile')

@section('profile-content')
<div class="d-flex flex-wrap gap-2 mb-4">
        @foreach (['' => 'All', 'passed' => 'Passed', 'failed' => 'Failed', 'in_progress' => 'In progress'] as $k => $l)
            <a href="{{ route('website.profile.quizzes') }}{{ $k ? '?status=' . $k : '' }}"
               class="btn btn-sm {{ request('status', '') === $k ? 'w-btn-primary' : 'w-btn-outline' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if ($attempts->count())
        <div class="w-card">
            <div class="w-table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Quiz</th><th>Category</th><th>Score</th><th>Result</th><th>Time</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($attempts as $a)
                            <tr>
                                <td>{{ $a->quiz->title ?? '—' }}</td>
                                <td class="w-text-sm w-muted">{{ $a->quiz->category->name ?? '—' }}</td>
                                <td>{{ $a->status === 'completed' ? $a->percentage . '%' : '—' }}</td>
                                <td>
                                    @if ($a->status === 'completed')
                                        <span class="w-badge {{ $a->passed ? 'w-badge-free' : 'w-badge-hard' }}">{{ $a->passed ? 'Passed' : 'Failed' }}</span>
                                    @else
                                        <span class="w-badge w-badge-primary">In progress</span>
                                    @endif
                                </td>
                                <td class="w-text-sm">{{ $a->time_taken ? gmdate('i:s', $a->time_taken) : '—' }}</td>
                                <td class="w-text-sm w-muted">{{ showDateTime($a->created_at, 'd M Y') }}</td>
                                <td>
                                    @if ($a->status === 'completed')
                                        <a href="{{ route('website.quiz.result', $a->id) }}" class="btn w-btn-outline btn-sm">Result</a>
                                    @else
                                        <a href="{{ route('website.quiz.attempt', $a->id) }}" class="btn w-btn-primary btn-sm">Resume</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <x-website::pagination :paginator="$attempts" />
    @else
        <div class="w-card"><x-website::empty-state icon="bi-list-check" title="No attempts found"
            :actionUrl="route('website.quizzes')" actionLabel="Find a quiz" /></div>
    @endif
@endsection
