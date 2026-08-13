@props(['xp' => null, 'nextLevel' => null, 'compact' => false])
@php
    $total   = $xp->total_xp ?? 0;
    $level   = $xp->current_level ?? 1;
    $needed  = $nextLevel->required_xp ?? null;
    // Progress within the current level band, not against a lifetime total.
    $percent = $needed && $needed > 0 ? min(100, round(($total / $needed) * 100)) : 100;
@endphp
<div class="w-card w-xp-card">
    <div class="w-card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <small class="text-white-50 d-block">Current level</small>
                <strong style="font-size: var(--w-fs-2xl);">Level {{ $level }}</strong>
            </div>
            <div class="text-end">
                <small class="text-white-50 d-block">Total XP</small>
                <strong style="font-size: var(--w-fs-2xl);">{{ number_format($total) }}</strong>
            </div>
        </div>

        <div class="w-progress mb-2" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="Progress to next level">
            <div class="w-progress-bar" data-progress="{{ $percent }}" style="width: {{ $percent }}%"></div>
        </div>

        <div class="d-flex justify-content-between w-text-xs text-white-50">
            @if ($nextLevel)
                <span>{{ number_format($total) }} XP</span>
                <span>{{ number_format(max(0, $needed - $total)) }} XP to {{ $nextLevel->name }}</span>
            @else
                <span>Maximum level reached</span>
            @endif
        </div>
    </div>
</div>
