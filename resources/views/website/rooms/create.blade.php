@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/rooms.css') }}" rel="stylesheet">
@endpush

@section('content')
<section class="w-section">
    <div class="container" style="max-width: 720px;">

        <div class="text-center mb-4">
            <span class="w-badge w-badge-primary mb-2"><i class="bi bi-controller"></i> Multiplayer</span>
            <h1 class="h2 mb-1">Create a Quiz Room</h1>
            <p class="w-muted">Pick a quiz, invite friends with a room code, and play together.</p>
        </div>

        {{-- Step indicator --}}
        <ol class="w-stepper mb-4" id="wStepper">
            <li class="is-active" data-step="1"><span>1</span> Category</li>
            <li data-step="2"><span>2</span> Quiz</li>
            <li data-step="3"><span>3</span> Settings</li>
            <li data-step="4"><span>4</span> Create</li>
        </ol>

        <form method="POST" action="{{ route('website.rooms.store') }}" id="wRoomForm">
            @csrf
            <input type="hidden" name="quiz_id" id="wQuizId">

            <div class="w-card">
                <div class="w-card-body">

                    {{-- Step 1: Category --}}
                    <div class="w-step-panel" data-panel="1">
                        <h2 class="w-card-title">Select Category</h2>
                        @if ($categories->isEmpty())
                            <div class="w-empty text-center py-4">
                                <i class="bi bi-inbox" style="font-size:2rem;" aria-hidden="true"></i>
                                <p class="w-muted mb-0 mt-2">No quiz categories are available right now.</p>
                            </div>
                        @else
                            <div class="form-group">
                                <label for="wCategory" class="w-text-sm fw-bold">Category</label>
                                <select class="form-select" name="category_id" id="wCategory" required>
                                    <option value="">— Select a category —</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn w-btn-primary" data-next disabled id="wStep1Next">
                                    Continue <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Step 2: Quiz --}}
                    <div class="w-step-panel d-none" data-panel="2">
                        <h2 class="w-card-title">Select Quiz</h2>
                        <p class="w-text-sm w-muted mb-3">Category: <strong id="wCatLabel"></strong></p>

                        <div id="wQuizLoading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="w-muted mb-0 mt-2">Loading quizzes…</p>
                        </div>

                        <div id="wQuizEmpty" class="w-empty text-center py-4 d-none">
                            <i class="bi bi-inbox" style="font-size:2rem;" aria-hidden="true"></i>
                            <p class="w-muted mb-0 mt-2">No quizzes are available in this category.<br>Please select another category.</p>
                        </div>

                        <div class="w-quiz-options" id="wQuizList"></div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn w-btn-outline" data-back><i class="bi bi-chevron-left"></i> Back</button>
                            <button type="button" class="btn w-btn-primary" data-next disabled id="wStep2Next">Continue <i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>

                    {{-- Step 3: Settings --}}
                    <div class="w-step-panel d-none" data-panel="3">
                        <h2 class="w-card-title">Room Settings</h2>

                        <div class="row g-3">
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="wMaxPlayers" class="w-text-sm fw-bold">Max Players <span class="w-muted">(2–100)</span></label>
                                    <input type="number" class="form-control" name="max_players" id="wMaxPlayers" value="10" min="2" max="100" required>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="wQuestionCount" class="w-text-sm fw-bold">Questions <span class="w-muted" id="wQMax"></span></label>
                                    <input type="number" class="form-control" name="question_count" id="wQuestionCount" min="1">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label for="wTimeLimit" class="w-text-sm fw-bold">Time <span class="w-muted">(min)</span></label>
                                    <input type="number" class="form-control" name="time_limit" id="wTimeLimit" min="0" max="180">
                                </div>
                            </div>
                        </div>
                        <p class="w-text-sm w-muted mt-1 mb-0"><i class="bi bi-info-circle"></i> Leave Questions or Time empty to use the quiz defaults. Time <strong>0</strong> = no limit.</p>

                        <div class="form-group mt-3">
                            <label class="w-text-sm fw-bold d-block mb-2">Room Type</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <label class="w-radio-pill">
                                    <input type="radio" name="room_type" value="private" checked>
                                    <span><i class="bi bi-lock"></i> Private</span>
                                </label>
                                <label class="w-radio-pill">
                                    <input type="radio" name="room_type" value="public">
                                    <span><i class="bi bi-globe"></i> Public</span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn w-btn-outline" data-back><i class="bi bi-chevron-left"></i> Back</button>
                            <button type="button" class="btn w-btn-primary" data-next>Continue <i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>

                    {{-- Step 4: Review + Create --}}
                    <div class="w-step-panel d-none" data-panel="4">
                        <h2 class="w-card-title">Review &amp; Create</h2>
                        <div class="w-review">
                            <div class="w-review-row"><span>Category</span><strong id="wRevCat"></strong></div>
                            <div class="w-review-row"><span>Quiz</span><strong id="wRevQuiz"></strong></div>
                            <div class="w-review-row"><span>Max Players</span><strong id="wRevPlayers"></strong></div>
                            <div class="w-review-row"><span>Questions</span><strong id="wRevQuestions"></strong></div>
                            <div class="w-review-row"><span>Time Limit</span><strong id="wRevTime"></strong></div>
                            <div class="w-review-row"><span>Room Type</span><strong id="wRevType"></strong></div>
                        </div>

                        @error('quiz_id') <div class="w-alert w-alert-danger mt-3">{{ $message }}</div> @enderror

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn w-btn-outline" data-back><i class="bi bi-chevron-left"></i> Back</button>
                            <button type="submit" class="btn btn-success" id="wCreateBtn">
                                <i class="bi bi-plus-circle"></i> Create Room
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('website.rooms.join') }}" class="w-text-sm">Have a code? <strong>Join a room instead</strong></a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
    var quizzesUrl = "{{ route('website.rooms.quizzes') }}";
    var state = { step: 1, quiz: null, quizTitle: '' };

    function showStep(n) {
        state.step = n;
        $('.w-step-panel').addClass('d-none');
        $('.w-step-panel[data-panel="' + n + '"]').removeClass('d-none');
        $('#wStepper li').removeClass('is-active is-done').each(function () {
            var s = +$(this).data('step');
            if (s < n) $(this).addClass('is-done');
            if (s === n) $(this).addClass('is-active');
        });
        $('html,body').animate({ scrollTop: 0 }, 150);
    }

    // Step 1 → enable Continue when a category is chosen.
    $('#wCategory').on('change', function () {
        $('#wStep1Next').prop('disabled', !$(this).val());
    });

    // Load quizzes when moving into step 2.
    function loadQuizzes() {
        var catId = $('#wCategory').val();
        $('#wCatLabel').text($('#wCategory option:selected').text());
        $('#wQuizList').empty();
        $('#wQuizEmpty').addClass('d-none');
        $('#wQuizId').val('');
        state.quiz = null;
        $('#wStep2Next').prop('disabled', true);
        $('#wQuizLoading').removeClass('d-none');

        $.get(quizzesUrl, { category_id: catId }).done(function (res) {
            $('#wQuizLoading').addClass('d-none');
            if (!res.quizzes || !res.quizzes.length) {
                $('#wQuizEmpty').removeClass('d-none');
                return;
            }
            var html = '';
            $.each(res.quizzes, function (i, q) {
                html += '<label class="w-quiz-option">' +
                    '<input type="radio" name="quiz_pick" value="' + q.id + '" data-title="' + $('<div>').text(q.title).html() + '"' +
                    ' data-max="' + q.max_questions + '" data-defq="' + q.def_questions + '" data-deft="' + q.def_time + '">' +
                    '<span class="w-quiz-option-body">' +
                        '<strong class="w-quiz-title"></strong>' +
                        '<span class="w-quiz-meta">' + q.questions + ' Qs · ' + q.time_limit + ' · ' + q.difficulty + '</span>' +
                    '</span></label>';
            });
            $('#wQuizList').html(html);
            $('#wQuizList .w-quiz-title').each(function (i) { $(this).text(res.quizzes[i].title); });
        }).fail(function () {
            $('#wQuizLoading').addClass('d-none');
            $('#wQuizEmpty').removeClass('d-none').find('p').text('Could not load quizzes. Please try again.');
        });
    }

    // Quiz selection.
    $('#wQuizList').on('change', 'input[name=quiz_pick]', function () {
        state.quiz = $(this).val();
        state.quizTitle = $(this).data('title');
        state.maxQ = +$(this).data('max') || 1;
        state.defQ = +$(this).data('defq') || state.maxQ;
        state.defT = +$(this).data('deft') || 0;
        $('#wQuizId').val(state.quiz);
        $('#wQuizList .w-quiz-option').removeClass('is-selected');
        $(this).closest('.w-quiz-option').addClass('is-selected');
        $('#wStep2Next').prop('disabled', false);
    });

    // Navigation.
    $('[data-next]').on('click', function () {
        if (state.step === 1) { loadQuizzes(); showStep(2); }
        else if (state.step === 2) { if (!state.quiz) return; prefillSettings(); showStep(3); }
        else if (state.step === 3) { buildReview(); showStep(4); }
    });
    $('[data-back]').on('click', function () { showStep(state.step - 1); });

    // Bound the questions field to the quiz and prefill with quiz defaults.
    function prefillSettings() {
        $('#wQMax').text('(max ' + state.maxQ + ')');
        $('#wQuestionCount').attr('max', state.maxQ).val(state.defQ);
        $('#wTimeLimit').val(state.defT);
    }

    function buildReview() {
        // Keep the questions field within the quiz's bank.
        var q = parseInt($('#wQuestionCount').val(), 10);
        if (q > state.maxQ) $('#wQuestionCount').val(state.maxQ);
        if (q < 1 || isNaN(q)) $('#wQuestionCount').val(state.defQ);

        var t = $('#wTimeLimit').val();
        $('#wRevCat').text($('#wCategory option:selected').text());
        $('#wRevQuiz').text(state.quizTitle);
        $('#wRevPlayers').text($('#wMaxPlayers').val());
        $('#wRevQuestions').text($('#wQuestionCount').val());
        $('#wRevTime').text(t === '' ? 'Quiz default' : (t === '0' ? 'No limit' : t + ' min'));
        $('#wRevType').text($('input[name=room_type]:checked').val() === 'public' ? 'Public' : 'Private');
    }

    // Prevent duplicate room creation on double-click.
    $('#wRoomForm').on('submit', function () {
        $('#wCreateBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Creating room…');
    });

    // If the server bounced back with a validation error, land on the review step.
    @if ($errors->any()) showStep(4); @endif
});
</script>
@endpush
