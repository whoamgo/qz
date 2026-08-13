<div class="mb-4"><x-website::xp-progress :xp="$xp" :nextLevel="$nextLevel" /></div>

<div class="w-card"><div class="w-card-body">
    <h2 class="w-card-title">XP History</h2>
    @forelse ($transactions as $txn)
        <div class="w-xp-txn">
            <div>
                <strong class="d-block">{{ $txn->description ?? ucfirst(str_replace('_', ' ', $txn->type ?? 'XP')) }}</strong>
                <small class="w-muted">{{ showDateTime($txn->created_at, 'd M Y, h:i A') }}</small>
            </div>
            <span class="w-xp-amount {{ ($txn->amount ?? 0) >= 0 ? 'is-plus' : 'is-minus' }}">
                {{ ($txn->amount ?? 0) >= 0 ? '+' : '' }}{{ number_format($txn->amount ?? 0) }} XP
            </span>
        </div>
    @empty
        <x-website::empty-state icon="bi-lightning-charge" title="No XP earned yet"
            message="Complete a quiz to start earning XP."
            :actionUrl="route('website.quizzes')" actionLabel="Start a quiz" />
    @endforelse
</div></div>
@if ($transactions->hasPages())<x-website::pagination :paginator="$transactions" />@endif
