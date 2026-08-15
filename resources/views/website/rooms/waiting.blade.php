@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/rooms.css') }}" rel="stylesheet">
@endpush

@section('content')
<section class="w-section">
    <div class="container" style="max-width: 760px;">

        <div class="text-center mb-4">
            <span class="w-badge w-badge-success mb-2"><i class="bi bi-check-circle"></i> Room Created</span>
            <h1 class="h2 mb-1">{{ $room->quiz->title }}</h1>
            <p class="w-muted">{{ $room->category->name }}</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <span class="w-badge w-badge-primary"><i class="bi bi-list-ol"></i>
                    {{ $room->question_count ?: $room->quiz->effectiveQuestionCount() }} questions</span>
                <span class="w-badge w-badge-primary"><i class="bi bi-clock"></i>
                    @php $t = $room->time_limit ?? $room->quiz->time_limit; @endphp
                    {{ $t ? $t . ' min' : 'No time limit' }}</span>
            </div>
        </div>

        <div class="row g-4">
            {{-- Room code + share --}}
            <div class="col-lg-5">
                <div class="w-card">
                    <div class="w-card-body text-center">
                        <p class="w-text-sm w-muted mb-1">Room Code</p>
                        <div class="w-room-code" id="wRoomCode">{{ $room->room_code }}</div>
                        <p class="w-text-sm w-muted mt-2 mb-3">Share this code with your friends.</p>

                        <button type="button" class="btn w-btn-outline w-100 mb-2" id="wCopyBtn">
                            <i class="bi bi-clipboard"></i> Copy Room Code
                        </button>
                        <button type="button" class="btn w-btn-outline w-100" id="wShareBtn">
                            <i class="bi bi-share"></i> Share
                        </button>
                    </div>
                </div>
            </div>

            {{-- Players + controls --}}
            <div class="col-lg-7">
                <div class="w-card">
                    <div class="w-card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="w-card-title mb-0">Players</h2>
                            <span class="w-badge w-badge-primary"><span id="wCount">{{ $room->currentPlayerCount() }}</span> / {{ $room->max_players }}</span>
                        </div>

                        <ul class="w-players" id="wPlayers"></ul>

                        <p class="w-text-sm w-muted text-center mt-3 mb-0" id="wWaitMsg">
                            <span class="spinner-border spinner-border-sm"></span> Waiting for players…
                        </p>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    @if ($isHost)
                        <form method="POST" action="{{ route('website.rooms.start', $room->id) }}" class="flex-grow-1" id="wStartForm">
                            @csrf
                            <button type="submit" class="btn btn-success w-100" id="wStartBtn">
                                <i class="bi bi-play-fill"></i> Start Quiz
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn w-btn-primary flex-grow-1" disabled>
                            <span class="spinner-border spinner-border-sm"></span> Waiting for host to start…
                        </button>
                    @endif

                    <form method="POST" action="{{ route('website.rooms.leave', $room->id) }}"
                          onsubmit="return confirm('Leave this room?');">
                        @csrf
                        <button type="submit" class="btn w-btn-outline"><i class="bi bi-box-arrow-left"></i> Leave</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="w-toast-copy" id="wCopyToast">Room code copied!</div>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
    var statusUrl = "{{ route('website.rooms.status', $room->id) }}";
    var code = "{{ $room->room_code }}";
    var joinUrl = "{{ route('website.rooms.join') }}";
    var isHost = @json($isHost);
    var started = false;

    // ---- Copy code -------------------------------------------------------
    function toast(msg) {
        var $t = $('#wCopyToast').text(msg).addClass('is-show');
        setTimeout(function () { $t.removeClass('is-show'); }, 1800);
    }
    $('#wCopyBtn').on('click', function () {
        navigator.clipboard.writeText(code).then(function () { toast('Room code copied!'); })
            .catch(function () { toast('Copy failed — code is ' + code); });
    });

    // ---- Share -----------------------------------------------------------
    var shareText = "Join my Quiz Mitra quiz room!\n\nRoom Code: " + code +
                    "\n\nJoin Quiz Mitra and enter the room code to participate.\n" + joinUrl;
    $('#wShareBtn').on('click', function () {
        if (navigator.share) {
            navigator.share({ title: 'Quiz Mitra Room', text: shareText }).catch(function () {});
        } else {
            window.open('https://wa.me/?text=' + encodeURIComponent(shareText), '_blank');
        }
    });

    // ---- Poll room status ------------------------------------------------
    function renderPlayers(list) {
        var html = '';
        $.each(list, function (i, p) {
            html += '<li class="w-player">' +
                '<span class="w-player-avatar">' + (p.name ? p.name.charAt(0).toUpperCase() : '?') + '</span>' +
                '<span class="w-player-name"></span>' +
                (p.is_host ? '<span class="w-badge w-badge-warning ms-auto">Host</span>' :
                 (p.is_you ? '<span class="w-badge w-badge-primary ms-auto">You</span>' : '')) +
                '</li>';
        });
        $('#wPlayers').html(html);
        $('#wPlayers .w-player-name').each(function (i) { $(this).text(list[i].name); });
    }

    function poll() {
        $.get(statusUrl).done(function (res) {
            $('#wCount').text(res.players);
            renderPlayers(res.participants);

            if (res.status === 'started' && res.play_url && !started) {
                started = true;
                $('#wWaitMsg').html('<strong>Quiz is starting…</strong>');
                window.location = res.play_url;
                return;
            }
            if (res.status === 'cancelled') {
                window.location = joinUrl;
                return;
            }
        }).always(function () {
            if (!started) setTimeout(poll, 3000);
        });
    }
    poll();

    // Host start: disable to prevent double submit.
    $('#wStartForm').on('submit', function () {
        started = true;
        $('#wStartBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Starting…');
    });
});
</script>
@endpush
