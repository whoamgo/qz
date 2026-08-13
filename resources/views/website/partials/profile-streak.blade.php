<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="w-card"><div class="w-card-body text-center">
            <div class="w-streak-flame"><i class="bi bi-fire"></i></div>
            <strong class="d-block" style="font-size: var(--w-fs-3xl);">{{ $stats['streak'] }}</strong>
            <span class="w-muted">Current streak (days)</span>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="w-card"><div class="w-card-body text-center">
            <div class="w-streak-flame"><i class="bi bi-trophy"></i></div>
            <strong class="d-block" style="font-size: var(--w-fs-3xl);">{{ $stats['longest'] }}</strong>
            <span class="w-muted">Longest streak (days)</span>
        </div></div>
    </div>
</div>

<div class="w-card"><div class="w-card-body">
    <h2 class="w-card-title">Last 12 weeks</h2>
    @php
        $start = now()->subWeeks(12)->startOfWeek();
        $days = [];
        for ($d = $start->copy(); $d <= now(); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = $activity[$key] ?? 0;
        }
    @endphp
    <div class="w-heatmap">
        @foreach ($days as $date => $count)
            @php $lvl = $count == 0 ? '' : ($count == 1 ? 'l1' : ($count == 2 ? 'l2' : ($count <= 4 ? 'l3' : 'l4'))); @endphp
            <span class="w-heat-cell {{ $lvl }}" title="{{ $date }}: {{ $count }} {{ \Illuminate\Support\Str::plural('quiz', $count) }}"></span>
        @endforeach
    </div>
    <div class="d-flex align-items-center gap-2 mt-3 w-text-xs w-muted">
        Less
        <span class="w-heat-cell"></span><span class="w-heat-cell l1"></span>
        <span class="w-heat-cell l2"></span><span class="w-heat-cell l3"></span><span class="w-heat-cell l4"></span>
        More
    </div>
</div></div>
