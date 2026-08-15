@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/rooms.css') }}" rel="stylesheet">
@endpush

@php
    $negative = (float) ($room->quiz->negative_marking ?? 0);
    $resultFaqs = [
        ['question' => 'How is the winner decided?', 'answer' => 'The player with the highest score is ranked first. If two players have the same score, the one who finished faster is ranked higher.'],
        ['question' => 'What does a score like 2 / 5 mean?', 'answer' => 'It is your score out of the total marks for this quiz — you earn ' . rtrim(rtrim(number_format((float)($room->quiz->marks_per_correct ?: 1), 2), '0'), '.') . ' mark(s) for each correct answer.'],
        ['question' => 'Why do I see a percentage?', 'answer' => 'The percentage is your score shown as a share of the total marks available in the quiz.'],
        ['question' => 'What do the ✓ and ✗ mean?', 'answer' => 'They are your correct (✓) and wrong (✗) answers. The clock next to them is the time you took to finish.'],
        ['question' => 'When are the final results shown?', 'answer' => 'Ranks update live as each player finishes. Once every player has finished, the standings lock in as the final results.'],
        ['question' => 'Do I still earn XP from a room quiz?', 'answer' => 'Yes — you earn the same XP and badges as any normal quiz you play.'],
    ];
@endphp

@section('content')
<section class="w-section">
    <div class="container" style="max-width: 1040px;">

        <div class="text-center mb-4">
            <span class="w-badge w-badge-primary mb-2"><i class="bi bi-trophy"></i> Room Leaderboard</span>
            <h1 class="h2 mb-1">{{ $room->quiz->title }}</h1>
            <p class="w-muted">{{ $room->category->name }} · Room {{ $room->room_code }}</p>
        </div>

        <div class="row g-4 align-items-start">
            {{-- Left: the leaderboard --}}
            <div class="col-lg-7 col-xl-8">
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

                <div class="d-flex gap-2 mt-3 flex-wrap">
                    @if ($isHost)
                        <form method="POST" action="{{ route('website.rooms.replay', $room->id) }}" class="flex-grow-1" id="wReplayForm">
                            @csrf
                            <button type="submit" class="btn btn-success w-100" id="wReplayBtn"><i class="bi bi-arrow-repeat"></i> Play Again</button>
                        </form>
                    @endif
                    <a href="{{ route('website.rooms.create') }}" class="btn w-btn-outline flex-grow-1"><i class="bi bi-plus-circle"></i> New Room</a>
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline flex-grow-1">Browse Quizzes</a>
                </div>
                @if (!$isHost)
                    <p class="w-text-sm w-muted text-center mt-2 mb-0"><i class="bi bi-info-circle"></i> The host can start a new round — you'll be taken there automatically.</p>
                @endif
            </div>

            {{-- Right: how ranking works + FAQ --}}
            <div class="col-lg-5 col-xl-4">
                <div class="w-card mb-3">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-info-circle"></i> How ranking works</h2>
                        <ul class="w-rules">
                            <li>
                                <span class="w-rule-num">1</span>
                                <div><strong>Score comes first</strong><br>
                                    <span class="w-text-sm w-muted">Players are ranked by total score — more correct answers means a higher rank.</span></div>
                            </li>
                            <li>
                                <span class="w-rule-num">2</span>
                                <div><strong>Faster finish breaks ties</strong><br>
                                    <span class="w-text-sm w-muted">If two players score the same, whoever finished quicker is ranked higher.</span></div>
                            </li>
                            <li>
                                <span class="w-rule-num">3</span>
                                <div><strong>Live, then final</strong><br>
                                    <span class="w-text-sm w-muted">Ranks update live as players finish; positions lock once everyone is done.</span></div>
                            </li>
                            @if ($negative > 0)
                            <li>
                                <span class="w-rule-num"><i class="bi bi-dash"></i></span>
                                <div><strong>Negative marking</strong><br>
                                    <span class="w-text-sm w-muted">{{ rtrim(rtrim(number_format($negative, 2), '0'), '.') }} is deducted for each wrong answer, so guessing can lower your score.</span></div>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="w-card">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-question-circle"></i> Result FAQ</h2>
                        <x-website::faq-accordion :faqs="$resultFaqs" id="wResultFaq" :title="null" />
                    </div>
                </div>
            </div>
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

    var waitingUrl = "{{ route('website.rooms.waiting', $room->id) }}";
    var replaying = false;

    render(initial);

    function poll() {
        if (replaying) { return; }
        $.get(dataUrl).done(function (board) {
            // A host "Play Again" sends the room back to waiting — pull everyone
            // (host included) into the new round automatically.
            if (board.status === 'waiting') { replaying = true; window.location = waitingUrl; return; }
            render(board);
            // Poll fast while playing, slower once finished (to catch a replay).
            setTimeout(poll, board.status === 'completed' ? 5000 : 3000);
        }).fail(function () { setTimeout(poll, 5000); });
    }
    poll();

    // Host replay: avoid a double submit.
    $('#wReplayForm').on('submit', function () {
        replaying = true;
        $('#wReplayBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Starting new round…');
    });
});
</script>
@endpush
