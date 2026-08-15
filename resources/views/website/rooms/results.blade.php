@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/rooms.css') }}" rel="stylesheet">
@endpush

@section('content')
<section class="w-section">
    <div class="container" style="max-width: 720px;">

        <div class="text-center mb-4">
            <span class="w-badge w-badge-primary mb-2"><i class="bi bi-trophy"></i> Room Leaderboard</span>
            <h1 class="h2 mb-1">{{ $room->quiz->title }}</h1>
            <p class="w-muted">{{ $room->category->name }} · Room {{ $room->room_code }}</p>
        </div>

        <div class="w-card">
            <div class="w-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="w-card-title mb-0" id="wBoardTitle">
                        {{ $board['status'] === 'completed' ? 'Final Results' : 'Live Standings' }}
                    </h2>
                    <span class="w-badge w-badge-primary">
                        <span id="wFinished">{{ $board['finished'] }}</span> / {{ $board['total'] }} finished
                    </span>
                </div>

                <ol class="w-board" id="wBoard"></ol>

                <p class="w-text-sm w-muted text-center mt-3 mb-0 d-none" id="wBoardWait">
                    <span class="spinner-border spinner-border-sm"></span> Waiting for players to finish…
                </p>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <a href="{{ route('website.rooms.create') }}" class="btn w-btn-outline flex-grow-1">
                <i class="bi bi-plus-circle"></i> New Room
            </a>
            <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline flex-grow-1">Browse Quizzes</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
    var dataUrl = "{{ route('website.rooms.results.data', $room->id) }}";
    var initial = @json($board);

    var medals = { 1: '🥇', 2: '🥈', 3: '🥉' };

    function render(board) {
        $('#wFinished').text(board.finished);
        $('#wBoardTitle').text(board.status === 'completed' ? 'Final Results' : 'Live Standings');
        $('#wBoardWait').toggleClass('d-none', board.status === 'completed');

        var html = '';
        $.each(board.rows, function (i, r) {
            var rankBadge = r.finished
                ? (medals[r.rank] || ('<span class="w-board-rank">' + r.rank + '</span>'))
                : '<span class="w-board-rank is-playing">•</span>';

            var right = r.finished
                ? '<span class="w-board-score">' + r.score + '<small>/' + r.total + '</small></span>' +
                  '<span class="w-board-meta">' + r.correct + '✓ ' + r.wrong + '✗ · ' + r.percentage + '% · ' + r.time + '</span>'
                : '<span class="w-board-meta">' + (r.playing ? 'Playing…' : 'Not started') + '</span>';

            html += '<li class="w-board-row' + (r.is_you ? ' is-you' : '') + (r.finished ? '' : ' is-pending') + '">' +
                '<span class="w-board-pos">' + rankBadge + '</span>' +
                '<span class="w-player-avatar">' + (r.name ? r.name.charAt(0).toUpperCase() : '?') + '</span>' +
                '<span class="w-board-name"></span>' +
                (r.is_host ? '<span class="w-badge w-badge-warning ms-1">Host</span>' : '') +
                (r.is_you ? '<span class="w-badge w-badge-primary ms-1">You</span>' : '') +
                '<span class="w-board-right ms-auto text-end">' + right + '</span>' +
                '</li>';
        });
        $('#wBoard').html(html);
        $('#wBoard .w-board-name').each(function (i) { $(this).text(board.rows[i].name); });
    }

    render(initial);

    function poll() {
        $.get(dataUrl).done(function (board) {
            render(board);
            if (board.status !== 'completed') setTimeout(poll, 3000);
        }).fail(function () { setTimeout(poll, 5000); });
    }
    if (initial.status !== 'completed') poll();
});
</script>
@endpush
