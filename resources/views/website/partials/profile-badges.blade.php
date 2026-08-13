<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h5 mb-0">Badges</h2>
    <span class="w-muted w-text-sm">{{ $earned->count() }} of {{ $allBadges->count() }} unlocked</span>
</div>

@if ($allBadges->count())
    <div class="row g-3">
        @foreach ($allBadges as $badge)
            <div class="col-6 col-md-4 col-lg-3">
                <x-website::badge-card :badge="$badge" :earned="$earned->has($badge->id)" />
            </div>
        @endforeach
    </div>
@else
    <div class="w-card"><x-website::empty-state icon="bi-award" title="No badges configured" /></div>
@endif
