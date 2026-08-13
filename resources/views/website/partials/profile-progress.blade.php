<div class="w-stat-grid mb-4">
    @foreach ([
        ['Average score', $stats['avg_score'] . '%'],
        ['Accuracy', $stats['accuracy'] . '%'],
        ['Quizzes', $stats['attempts']],
        ['Time spent', gmdate('H:i', $stats['total_time'])],
    ] as [$label, $value])
        <div class="w-stat-tile"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
    @endforeach
</div>

<div class="w-card mb-4"><div class="w-card-body">
    <h2 class="w-card-title">Accuracy by category</h2>
    @forelse ($byCategory as $row)
        @php $acc = $row->answered > 0 ? round(($row->correct / $row->answered) * 100) : 0; @endphp
        <div class="w-cat-progress">
            <div class="w-cat-progress-head">
                <a href="{{ route('website.category.show', $row->slug) }}">{{ $row->name }}</a>
                <span class="w-muted">{{ $acc }}% &middot; {{ $row->attempts }} {{ \Illuminate\Support\Str::plural('quiz', $row->attempts) }}</span>
            </div>
            <div class="w-progress">
                <div class="w-progress-bar {{ $acc >= 70 ? 'is-success' : ($acc >= 40 ? 'is-warning' : 'is-danger') }}"
                     data-progress="{{ $acc }}" style="width: {{ $acc }}%"></div>
            </div>
        </div>
    @empty
        <x-website::empty-state icon="bi-graph-up" title="No data yet"
            message="Complete a few quizzes and your per-category accuracy will appear here." />
    @endforelse
</div></div>

@if ($timeline->count())
    <div class="w-card"><div class="w-card-body">
        <h2 class="w-card-title">Last 30 days</h2>
        <div class="w-table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Date</th><th>Quizzes</th><th>Avg score</th><th>XP</th></tr></thead>
                <tbody>
                    @foreach ($timeline->reverse() as $day)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($day->day)->format('d M Y') }}</td>
                            <td>{{ $day->attempts }}</td>
                            <td>{{ round($day->avg_score) }}%</td>
                            <td>+{{ number_format($day->xp) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
@endif
