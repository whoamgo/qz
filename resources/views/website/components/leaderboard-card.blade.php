@props(['row', 'rank', 'isMe' => false])
@php
    $u = $row->user;
    $name = trim(($u->firstname ?? '') . ' ' . ($u->lastname ?? '')) ?: ($u->username ?? 'User');
@endphp
<div class="d-flex align-items-center gap-3 p-3 w-rank-row {{ $isMe ? 'is-me' : '' }}" style="border-bottom: 1px solid var(--w-border);">
    <span class="fw-bold text-center flex-shrink-0" style="width: 40px; font-size: var(--w-fs-lg);">
        @if ($rank === 1) <i class="bi bi-trophy-fill text-warning" aria-label="Rank 1"></i>
        @elseif ($rank === 2) <i class="bi bi-trophy-fill" style="color:#94a3b8;" aria-label="Rank 2"></i>
        @elseif ($rank === 3) <i class="bi bi-trophy-fill" style="color:#fb923c;" aria-label="Rank 3"></i>
        @else {{ $rank }} @endif
    </span>
    <img class="w-avatar" src="{{ getImage(getFilePath('userProfile') . '/' . ($u->image ?? ''), getFileSize('userProfile')) }}"
         alt="{{ $name }}" width="36" height="36" loading="lazy">
    <div class="flex-grow-1 min-width-0">
        <strong class="d-block text-truncate">{{ $name }} @if($isMe)<span class="w-badge w-badge-primary ms-1">You</span>@endif</strong>
        <small class="w-muted">Level {{ $row->level }}@if(!is_null($row->attempts)) &middot; {{ $row->attempts }} quizzes @endif</small>
    </div>
    <span class="w-xp-pill flex-shrink-0"><i class="bi bi-lightning-charge-fill"></i> {{ number_format($row->xp) }}</span>
</div>
