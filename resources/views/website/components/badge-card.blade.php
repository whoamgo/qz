@props(['badge', 'earned' => false, 'earnedAt' => null])
<div class="w-badge-tile {{ $earned ? '' : 'is-locked' }}">
    <span class="w-badge-icon" style="background: {{ $badge->color ?: 'var(--w-primary)' }};" aria-hidden="true">
        @if ($badge->icon) @php echo $badge->icon; @endphp @else <i class="bi bi-award-fill"></i> @endif
    </span>
    <h4>{{ $badge->name }}</h4>
    <p>{{ $badge->description }}</p>
    @if ($badge->reward_xp)
        <span class="w-badge w-badge-primary mt-2"><i class="bi bi-lightning-charge-fill"></i> {{ $badge->reward_xp }} XP</span>
    @endif
    @if ($earned)
        <div class="w-text-xs text-success mt-2"><i class="bi bi-check-circle-fill"></i> Earned</div>
    @else
        <div class="w-text-xs w-muted mt-2"><i class="bi bi-lock-fill"></i> Locked</div>
    @endif
</div>
