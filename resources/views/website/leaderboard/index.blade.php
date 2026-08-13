@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Leaderboard' => route('website.leaderboard')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>Leaderboard</h1>
                <p>Top performers ranked by XP earned.</p>
            </div>
        </div>

        {{-- Period + category filters --}}
        <form class="wFilterForm mb-4" action="{{ route('website.leaderboard') }}" method="GET">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'all_time' => 'All Time'] as $k => $l)
                    <a href="{{ route('website.leaderboard') }}?period={{ $k }}{{ $category ? '&category=' . $category->slug : '' }}"
                       class="btn btn-sm {{ $period === $k ? 'w-btn-primary' : 'w-btn-outline' }}">{{ $l }}</a>
                @endforeach

                <select name="category" class="form-select form-select-sm ms-auto" style="max-width: 240px;" aria-label="Filter by category">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->slug }}" @selected($category?->slug === $cat->slug)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="period" value="{{ $period }}">
            </div>
        </form>

        @if ($leaders->count())
            {{-- Podium for the top three --}}
            @if ($leaders->count() >= 3)
                <div class="w-podium mb-5">
                    @foreach ([1 => $leaders[1] ?? null, 0 => $leaders[0] ?? null, 2 => $leaders[2] ?? null] as $i => $row)
                        @continue(!$row)
                        @php $rank = $i + 1; @endphp
                        <div class="w-podium-item w-podium-{{ $rank }}">
                            <img class="w-podium-avatar"
                                 src="{{ getImage(getFilePath('userProfile') . '/' . ($row->user->image ?? ''), getFileSize('userProfile')) }}"
                                 alt="" width="62" height="62" loading="lazy">
                            <div class="fw-bold text-truncate">{{ trim(($row->user->firstname ?? '') . ' ' . ($row->user->lastname ?? '')) ?: $row->user->username }}</div>
                            <small class="w-muted d-block mb-2">{{ number_format($row->xp) }} XP</small>
                            <div class="w-podium-block d-flex align-items-center justify-content-center fs-4">{{ $rank }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="w-card">
                @foreach ($leaders as $i => $row)
                    <x-website::leaderboard-card :row="$row" :rank="$i + 1"
                        :isMe="auth()->check() && (int) auth()->id() === (int) $row->user_id" />
                @endforeach
            </div>

            @auth
                @if (!$myRank && $myRow)
                    <div class="w-card mt-3">
                        <div class="px-3 py-2 w-text-sm w-muted">Your position</div>
                        <x-website::leaderboard-card :row="$myRow" :rank="'—'" :isMe="true" />
                    </div>
                @endif
            @endauth
        @else
            <div class="w-card">
                <x-website::empty-state icon="bi-trophy"
                    title="No ranked players yet"
                    message="Complete a quiz to earn XP and be the first on this leaderboard."
                    :actionUrl="route('website.quizzes')" actionLabel="Start a quiz" />
            </div>
        @endif
    </div>
</section>
@endsection
