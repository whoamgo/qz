@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/rooms.css') }}" rel="stylesheet">
@endpush

@section('content')
<section class="w-section">
    <div class="container" style="max-width: 520px;">

        <div class="text-center mb-4">
            <span class="w-badge w-badge-primary mb-2"><i class="bi bi-box-arrow-in-right"></i> Join</span>
            <h1 class="h2 mb-1">Join a Quiz Room</h1>
            <p class="w-muted">Enter the room code your host shared with you.</p>
        </div>

        @if (session('info'))  <div class="w-alert w-alert-info mb-3">{{ session('info') }}</div>  @endif
        @if (session('error')) <div class="w-alert w-alert-danger mb-3">{{ session('error') }}</div> @endif

        <div class="w-card">
            <div class="w-card-body">
                {{-- Enter code --}}
                <div id="wCodeStep">
                    <div class="form-group">
                        <label for="wRoomCode" class="w-text-sm fw-bold">Room Code</label>
                        <input type="text" class="form-control w-code-input" id="wRoomCode"
                               maxlength="12" placeholder="QZ7K9P" autocomplete="off" autocapitalize="characters">
                    </div>
                    <div class="w-alert w-alert-danger mt-2 d-none" id="wCodeError"></div>
                    <button type="button" class="btn w-btn-primary w-100 mt-3" id="wPreviewBtn">Find Room</button>
                </div>

                {{-- Room preview --}}
                <div id="wPreviewStep" class="d-none">
                    <div class="w-review mb-3">
                        <div class="w-review-row"><span>Quiz</span><strong id="wPvQuiz"></strong></div>
                        <div class="w-review-row"><span>Category</span><strong id="wPvCat"></strong></div>
                        <div class="w-review-row"><span>Host</span><strong id="wPvHost"></strong></div>
                        <div class="w-review-row"><span>Players</span><strong id="wPvPlayers"></strong></div>
                    </div>
                    <div class="w-alert w-alert-warning d-none" id="wPvBlock"></div>

                    <form method="POST" action="{{ route('website.rooms.join.store') }}" id="wJoinForm">
                        @csrf
                        <input type="hidden" name="room_code" id="wJoinCode">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn w-btn-outline" id="wBackBtn"><i class="bi bi-chevron-left"></i> Back</button>
                            <button type="submit" class="btn btn-success flex-grow-1" id="wJoinBtn">Join This Room</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="{{ route('website.rooms.create') }}" class="w-text-sm">Want to host? <strong>Create a room</strong></a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
    var previewUrl = "{{ route('website.rooms.preview') }}";
    var token = "{{ csrf_token() }}";

    $('#wRoomCode').on('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });

    function showError(msg) {
        $('#wCodeError').text(msg).removeClass('d-none');
    }

    $('#wPreviewBtn').on('click', function () {
        var code = $('#wRoomCode').val().trim();
        $('#wCodeError').addClass('d-none');
        if (code.length < 4) { showError('Please enter a valid room code.'); return; }

        var $btn = $(this).prop('disabled', true).text('Finding…');

        $.post(previewUrl, { _token: token, room_code: code }).done(function (res) {
            var r = res.room;
            $('#wPvQuiz').text(r.quiz);
            $('#wPvCat').text(r.category);
            $('#wPvHost').text(r.host);
            $('#wPvPlayers').text(r.players + ' / ' + r.max_players);
            $('#wJoinCode').val(r.code);

            if (r.already_in) {
                // Already a member → let them go straight back in.
                $('#wPvBlock').removeClass('d-none').text(r.message);
                $('#wJoinBtn').text('Return to Room').prop('disabled', false);
            } else if (!r.joinable) {
                $('#wPvBlock').removeClass('d-none').text(r.message || 'This room cannot be joined.');
                $('#wJoinBtn').prop('disabled', true);
            } else {
                $('#wPvBlock').addClass('d-none');
                $('#wJoinBtn').text('Join This Room').prop('disabled', false);
            }

            $('#wCodeStep').addClass('d-none');
            $('#wPreviewStep').removeClass('d-none');
        }).fail(function (xhr) {
            var msg = 'No room was found with that code.';
            if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.room_code) {
                msg = xhr.responseJSON.errors.room_code[0];
            }
            showError(msg);
        }).always(function () {
            $btn.prop('disabled', false).text('Find Room');
        });
    });

    $('#wBackBtn').on('click', function () {
        $('#wPreviewStep').addClass('d-none');
        $('#wCodeStep').removeClass('d-none');
    });

    $('#wJoinForm').on('submit', function () {
        $('#wJoinBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Joining…');
    });

    // Shared link (?code=QZ7K9P): pre-fill the code and preview automatically,
    // so a friend who tapped the link only has to confirm "Join This Room".
    var params = new URLSearchParams(window.location.search);
    var sharedCode = (params.get('code') || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (sharedCode.length >= 4) {
        $('#wRoomCode').val(sharedCode);
        $('#wPreviewBtn').trigger('click');
    }
});
</script>
@endpush
