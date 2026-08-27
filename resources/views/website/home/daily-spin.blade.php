{{--
    Daily Spin & Win — homepage gamification section.

    Self-contained: markup + scoped CSS (@push styles) + jQuery logic (@push scripts).
    The wheel is purely visual; every decision (eligibility, landed segment, question,
    correctness, XP) is made by the backend via daily-spin/{status,spin,answer}.
    Bootstrap 5 + jQuery are already loaded by the layout.
--}}
@php
    use App\Services\DailySpinService;

    $dsSegments = DailySpinService::segments();
    $dsCount    = count($dsSegments);
    // Saturated palette (all dark enough for white labels).
    $dsColors   = ['#7C3AED', '#059669', '#2563EB', '#DB2777', '#EA580C', '#0891B2', '#B45309', '#4F46E5'];

    // Pre-compute each segment's wedge path + label rotation for an 8-slice wheel.
    $cx = 150; $cy = 150; $r = 145; $seg = 360 / $dsCount;
    $dsWedges = [];
    foreach ($dsSegments as $i => $s) {
        $start = -90 + $i * $seg;        // degrees, 0° at 3 o'clock, clockwise
        $end   = $start + $seg;
        $sr = deg2rad($start); $er = deg2rad($end);
        $x1 = $cx + $r * cos($sr); $y1 = $cy + $r * sin($sr);
        $x2 = $cx + $r * cos($er); $y2 = $cy + $r * sin($er);
        $dsWedges[] = [
            'path'      => sprintf('M%.2f %.2f L%.2f %.2f A%d %d 0 0 1 %.2f %.2f Z', $cx, $cy, $x1, $y1, $r, $r, $x2, $y2),
            'color'     => $dsColors[$i % count($dsColors)],
            'labelRot'  => $i * $seg + $seg / 2,   // rotate top-anchored label to this wedge
            'emoji'     => $s['emoji'],
            'label'     => $s['label'],
        ];
    }
@endphp

<section id="dailySpin" class="w-section" aria-labelledby="dsHeading"
         data-auth="{{ auth()->check() ? '1' : '0' }}"
         data-status-url="{{ route('website.daily-spin.status') }}"
         @auth data-spin-url="{{ route('website.daily-spin.spin') }}" data-answer-url="{{ route('website.daily-spin.answer') }}" @endauth
         data-login-url="{{ route('user.login') }}">
    <div class="container">
        <div class="ds-card">
            <div class="row align-items-center g-4">

                {{-- Left: copy + controls --}}
                <div class="col-lg-6 order-2 order-lg-1 text-center text-lg-start">
                    <span class="ds-kicker">🎡 <span>Daily Spin &amp; Win</span></span>
                    <h2 id="dsHeading" class="ds-title">Spin the wheel, answer 10 questions, earn up to&nbsp;<span class="ds-xp-badge">+50 XP</span></h2>

                    @guest
                        <p class="ds-lead">🎁 Your daily reward is waiting! Log in to spin today's wheel and earn XP.</p>
                        <div class="d-flex gap-2 justify-content-center justify-content-lg-start flex-wrap">
                            <a href="{{ route('user.login') }}" class="btn ds-btn-primary btn-lg">🔐 Login Now</a>
                            <a href="{{ route('user.register') }}" class="btn ds-btn-ghost btn-lg">Create Free Account</a>
                        </div>
                    @else
                        <p class="ds-lead">Spin once a day and answer 10 questions — earn +5 XP for every correct answer (up to +50). One spin per day.</p>

                        {{-- Live status pill (filled by JS: available / completed) --}}
                        <div id="dsStatus" class="ds-statuspill ds-statuspill--loading" role="status" aria-live="polite">
                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            <span>Checking today's spin…</span>
                        </div>

                        <div class="mt-3 d-flex gap-2 justify-content-center justify-content-lg-start flex-wrap">
                            <button type="button" class="btn ds-btn-primary btn-lg ds-spin-trigger" disabled>
                                <span class="ds-spin-label">SPIN NOW</span>
                            </button>
                            <a href="{{ route('website.quizzes') }}" class="btn ds-btn-ghost btn-lg">Explore Quizzes</a>
                        </div>

                        {{-- Completed panel (hidden until the day is done) --}}
                        <div id="dsDonePanel" class="ds-done d-none" aria-live="polite">
                            <div class="ds-done-check">✓</div>
                            <div>
                                <strong>Today's Spin Completed</strong>
                                <div class="w-muted">You've already played today. Come back tomorrow for another chance to earn XP.</div>
                                <div class="ds-next">Next spin: <strong>Tomorrow</strong></div>
                            </div>
                        </div>
                    @endguest
                </div>

                {{-- Right: the wheel --}}
                <div class="col-lg-6 order-1 order-lg-2">
                    <div class="ds-wheel-wrap @guest ds-wheel-wrap--locked @endguest" aria-hidden="true">
                        <div class="ds-pointer"></div>
                        <svg class="ds-wheel" viewBox="0 0 300 300" role="img" aria-label="Prize wheel">
                            <g class="ds-wheel-rot">
                                @foreach ($dsWedges as $w)
                                    <path d="{{ $w['path'] }}" fill="{{ $w['color'] }}" stroke="#ffffff" stroke-width="2"></path>
                                @endforeach
                                @foreach ($dsWedges as $w)
                                    <g transform="rotate({{ $w['labelRot'] }} 150 150)">
                                        <text x="150" y="42" text-anchor="middle" class="ds-wedge-emoji">{{ $w['emoji'] }}</text>
                                        <text x="150" y="66" text-anchor="middle" class="ds-wedge-label">{{ \Illuminate\Support\Str::limit($w['label'], 14, '') }}</text>
                                    </g>
                                @endforeach
                            </g>
                            <circle cx="150" cy="150" r="30" fill="#ffffff" stroke="#e5e7eb" stroke-width="2"></circle>
                        </svg>

                        <button type="button" class="ds-hub ds-spin-trigger" @guest disabled @endguest aria-label="Spin the wheel">
                            <span class="ds-hub-text">SPIN</span>
                        </button>

                        @guest
                            <div class="ds-lock">
                                <div class="ds-lock-inner">🔒<span>Login to spin</span></div>
                            </div>
                        @endguest
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- Question / result modal --}}
@auth
<div class="modal fade" id="dsModal" tabindex="-1" aria-labelledby="dsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ds-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="dsModalTitle">🎉 You landed on <span id="dsLanded">—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Question step: the full set --}}
                <div id="dsQuestionStep">
                    <div class="ds-qhead">
                        <p class="ds-qlabel mb-0">Answer all <span class="ds-total">10</span> questions, then submit</p>
                        <span class="ds-progress"><span id="dsAnswered">0</span>/<span class="ds-total">10</span> answered</span>
                    </div>
                    <div id="dsQuestions" class="ds-qlist"></div>
                    <div id="dsAnswerError" class="ds-inline-error d-none" role="alert"></div>
                </div>

                {{-- Result step: score + review --}}
                <div id="dsResultStep" class="d-none">
                    <div class="text-center">
                        <div id="dsResultIcon" class="ds-result-icon"></div>
                        <h4 id="dsResultTitle" class="mb-1"></h4>
                        <div id="dsScoreLine" class="ds-score-line"></div>
                        <div id="dsXpGain" class="ds-xp-gain d-none">+<span id="dsXpAmount">0</span> XP</div>
                        <div class="ds-stats" id="dsStatsRow">
                            <div class="ds-stat"><span class="ds-stat-num" id="dsTotalXp">0</span><span class="ds-stat-cap">Total XP</span></div>
                            <div class="ds-stat"><span class="ds-stat-num" id="dsLevel">1</span><span class="ds-stat-cap">Level</span></div>
                        </div>
                    </div>
                    <p class="ds-qlabel mt-4 mb-2">Review</p>
                    <div id="dsReview" class="ds-review"></div>
                    <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                        <a href="{{ route('website.quizzes') }}" class="btn ds-btn-primary">Explore More Quizzes</a>
                        <button type="button" class="btn ds-btn-ghost" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0" id="dsFooter">
                <button type="button" id="dsSubmit" class="btn ds-btn-primary w-100" disabled>
                    <span class="ds-submit-label">Answer all 10 to submit</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endauth

<div id="dsConfetti" aria-hidden="true"></div>

@push('styles')
<style>
    /* ---- Daily Spin & Win ---- */
    .ds-card{background:linear-gradient(135deg,#eef2ff 0%,#faf5ff 45%,#fff 100%);border:1px solid #ece9fb;
        border-radius:20px;padding:28px;box-shadow:0 12px 40px rgba(76,29,149,.08)}
    .ds-kicker{display:inline-flex;align-items:center;gap:.4rem;font-weight:700;color:#7C3AED;
        background:#f3e8ff;border-radius:999px;padding:.35rem .8rem;font-size:.85rem;margin-bottom:.75rem}
    .ds-title{font-size:clamp(1.35rem,2.4vw,1.9rem);line-height:1.25;margin-bottom:.6rem}
    .ds-xp-badge{color:#059669;white-space:nowrap}
    .ds-lead{color:#475569;margin-bottom:1rem}
    .ds-btn-primary{background:linear-gradient(135deg,#7C3AED,#5b21b6);border:none;color:#fff;font-weight:700;
        border-radius:12px;box-shadow:0 6px 18px rgba(124,58,237,.35);transition:transform .15s ease,box-shadow .15s ease}
    .ds-btn-primary:hover:not(:disabled){color:#fff;transform:translateY(-1px);box-shadow:0 10px 24px rgba(124,58,237,.45)}
    .ds-btn-primary:disabled{opacity:.55;cursor:not-allowed}
    .ds-btn-ghost{background:#fff;border:1px solid #e2d9f7;color:#5b21b6;font-weight:700;border-radius:12px}
    .ds-btn-ghost:hover{background:#f7f2ff;color:#5b21b6}

    .ds-statuspill{display:inline-flex;align-items:center;gap:.5rem;border-radius:999px;padding:.45rem .9rem;
        font-weight:600;font-size:.92rem;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
    .ds-statuspill--loading{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
    .ds-statuspill--done{background:#ecfdf5;color:#047857;border-color:#a7f3d0}

    .ds-done{display:none;align-items:center;gap:.75rem;margin-top:1rem;padding:.9rem 1rem;background:#ecfdf5;
        border:1px solid #a7f3d0;border-radius:14px;text-align:left}
    .ds-done:not(.d-none){display:flex}
    .ds-done-check{flex:0 0 auto;width:38px;height:38px;border-radius:50%;background:#10b981;color:#fff;
        font-weight:800;display:grid;place-items:center;font-size:1.1rem}
    .ds-next{margin-top:.25rem;font-size:.9rem;color:#047857}

    /* Wheel */
    .ds-wheel-wrap{position:relative;width:100%;max-width:330px;margin:0 auto;aspect-ratio:1/1}
    .ds-wheel{width:100%;height:auto;display:block;filter:drop-shadow(0 10px 24px rgba(30,27,75,.18))}
    .ds-wheel-rot{transform-box:fill-box;transform-origin:150px 150px;
        transition:transform 4.2s cubic-bezier(.15,.75,.15,1)}
    .ds-wedge-emoji{font-size:20px}
    .ds-wedge-label{font-size:8.5px;font-weight:700;fill:#fff;letter-spacing:.2px}
    .ds-pointer{position:absolute;top:-6px;left:50%;transform:translateX(-50%);z-index:3;
        width:0;height:0;border-left:14px solid transparent;border-right:14px solid transparent;
        border-top:22px solid #1e1b4b;filter:drop-shadow(0 2px 3px rgba(0,0,0,.25))}
    .ds-hub{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:4;
        width:64px;height:64px;border-radius:50%;border:none;cursor:pointer;
        background:radial-gradient(circle at 35% 30%,#8b5cf6,#5b21b6);color:#fff;font-weight:800;font-size:.8rem;
        box-shadow:0 6px 16px rgba(91,33,182,.5),0 0 0 6px rgba(255,255,255,.85);
        transition:transform .15s ease}
    .ds-hub:hover:not(:disabled){transform:translate(-50%,-50%) scale(1.06)}
    .ds-hub:disabled{cursor:not-allowed;filter:grayscale(.3)}
    .ds-hub.is-spinning{animation:dsPulse 1s ease-in-out infinite}
    @keyframes dsPulse{50%{box-shadow:0 6px 16px rgba(91,33,182,.5),0 0 0 12px rgba(139,92,246,.15)}}

    .ds-wheel-wrap--locked .ds-wheel{filter:drop-shadow(0 10px 24px rgba(30,27,75,.18)) grayscale(.55);opacity:.8}
    .ds-lock{position:absolute;inset:0;display:grid;place-items:center;z-index:5}
    .ds-lock-inner{background:rgba(30,27,75,.82);color:#fff;border-radius:14px;padding:.7rem 1rem;font-weight:700;
        display:flex;flex-direction:column;align-items:center;gap:.2rem;font-size:1.4rem}
    .ds-lock-inner span{font-size:.8rem;font-weight:600}

    /* Modal */
    .ds-modal{border:none;border-radius:20px;overflow:hidden}
    .ds-qlabel{text-transform:uppercase;letter-spacing:.06em;font-size:.72rem;color:#7C3AED;font-weight:800;margin-bottom:.25rem}
    .ds-question{font-size:1.12rem;font-weight:700;color:#1e293b;margin-bottom:1rem}
    .ds-options{display:flex;flex-direction:column;gap:.55rem}
    .ds-opt{display:flex;align-items:center;gap:.65rem;border:1.5px solid #e5e7eb;border-radius:12px;padding:.7rem .85rem;
        cursor:pointer;transition:border-color .12s ease,background .12s ease;background:#fff}
    .ds-opt:hover{border-color:#c4b5fd;background:#faf5ff}
    .ds-opt input{accent-color:#7C3AED;width:18px;height:18px;flex:0 0 auto}
    .ds-opt.is-selected{border-color:#7C3AED;background:#f5f3ff}
    .ds-opt.is-correct{border-color:#10b981;background:#ecfdf5}
    .ds-opt.is-wrong{border-color:#ef4444;background:#fef2f2}
    .ds-opt-key{flex:0 0 auto;width:26px;height:26px;border-radius:7px;background:#f1f5f9;display:grid;place-items:center;
        font-weight:800;color:#475569;font-size:.85rem}
    .ds-opt-text{word-break:break-word}
    .ds-inline-error{margin-top:.75rem;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:.5rem .75rem;font-size:.9rem}

    /* 10-question set */
    .ds-qhead{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.9rem}
    .ds-progress{font-size:.82rem;font-weight:700;color:#5b21b6;background:#f5f3ff;border:1px solid #ede9fe;border-radius:999px;padding:.25rem .7rem;white-space:nowrap}
    .ds-qlist{display:flex;flex-direction:column;gap:14px}
    .ds-qcard{border:1px solid #eceaf6;border-radius:14px;padding:14px 16px;background:#fff}
    .ds-qcard.is-answered{border-color:#c4b5fd;background:#faf9ff}
    .ds-qtop{display:flex;gap:.6rem;align-items:flex-start;margin-bottom:.7rem}
    .ds-qnum{flex:0 0 auto;width:26px;height:26px;border-radius:8px;background:#7C3AED;color:#fff;font-weight:800;
        display:grid;place-items:center;font-size:.82rem;margin-top:1px}
    .ds-qtext{font-weight:700;color:#1e293b;line-height:1.4}
    .ds-review{display:flex;flex-direction:column;gap:10px}
    .ds-review-item{border:1px solid #eef2f7;border-radius:12px;padding:12px 14px;background:#fafbfc}
    .ds-review-item.is-correct{border-color:#a7f3d0;background:#f0fdf7}
    .ds-review-item.is-wrong{border-color:#fecaca;background:#fef5f5}
    .ds-review-q{font-weight:700;color:#1e293b;display:flex;gap:.5rem;line-height:1.4}
    .ds-review-mark{flex:0 0 auto}
    .ds-review-ans{font-size:.9rem;margin-top:.35rem}
    .ds-review-ans .ok{color:#047857;font-weight:600}
    .ds-review-ans .no{color:#b91c1c;font-weight:600}
    .ds-review-exp{font-size:.86rem;color:#64748b;margin-top:.3rem}
    .ds-score-line{font-size:1.05rem;font-weight:700;color:#334155;margin:.15rem 0 .35rem}
    #dsFooter{position:sticky;bottom:0}

    .ds-result-icon{font-size:3rem;line-height:1}
    .ds-xp-gain{font-size:1.5rem;font-weight:900;color:#059669;margin:.25rem 0}
    .ds-correct-line{font-weight:700;color:#334155;margin-top:.35rem}
    .ds-explain{color:#475569;font-size:.95rem;margin-top:.5rem;text-align:left;background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:.7rem .85rem}
    .ds-stats{display:flex;gap:1rem;justify-content:center;margin-top:1rem}
    .ds-stat{background:#f5f3ff;border:1px solid #ede9fe;border-radius:12px;padding:.6rem 1rem;min-width:96px}
    .ds-stat-num{display:block;font-size:1.35rem;font-weight:900;color:#5b21b6}
    .ds-stat-cap{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed}
    .ds-shake{animation:dsShake .4s ease}
    @keyframes dsShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-7px)}40%,80%{transform:translateX(7px)}}

    /* Confetti */
    #dsConfetti{position:fixed;inset:0;pointer-events:none;z-index:1090;overflow:hidden}
    .ds-confetti-piece{position:absolute;top:-12px;width:9px;height:14px;opacity:.95;will-change:transform;border-radius:2px}
    @keyframes dsFall{to{transform:translateY(105vh) rotate(720deg);opacity:.9}}

    @media (max-width:575.98px){
        .ds-card{padding:20px}
        .ds-wheel-wrap{max-width:280px}
    }
    @media (prefers-reduced-motion:reduce){
        .ds-wheel-rot{transition:transform .35s linear}
        .ds-hub.is-spinning{animation:none}
        .ds-confetti-piece{display:none}
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var $section = $('#dailySpin');
    if (!$section.length) return;

    var isAuth   = $section.data('auth') === 1 || $section.data('auth') === '1';
    var URLS = {
        status: $section.data('status-url'),
        spin:   $section.data('spin-url'),
        answer: $section.data('answer-url')
    };
    var CSRF = $('meta[name="csrf-token"]').attr('content');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function post(url, data) {
        return $.ajax({ url: url, method: 'POST', dataType: 'json',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, data: data || {} });
    }

    /* -------- Guests: static locked state, nothing else to wire -------- */
    if (!isAuth) return;

    var $status   = $('#dsStatus');
    var $triggers = $('.ds-spin-trigger');
    var $hub      = $('.ds-hub');
    var $rot      = $('.ds-wheel-rot');
    var $done     = $('#dsDonePanel');
    var modalEl   = document.getElementById('dsModal');
    var modal     = null;
    // Bootstrap's bundle is loaded with `defer`, so `bootstrap` is not defined
    // while this end-of-body script runs. Create the modal lazily (first use is
    // after a spin, by which point the deferred bundle has executed).
    function getModal() {
        if (!modal && modalEl && window.bootstrap) { modal = new bootstrap.Modal(modalEl); }
        return modal;
    }

    var rotation  = 0;         // accumulated wheel rotation (deg)
    var busy      = false;     // guards double spins / double submits
    var current   = null;      // { spin_id, options:[...] }
    var SEG_COUNT = {{ $dsCount }};

    function setStatusPill(kind, html) {
        $status.removeClass('ds-statuspill--loading ds-statuspill--done');
        if (kind === 'done') $status.addClass('ds-statuspill--done');
        $status.html(html);
    }
    function enableSpin(on) {
        $triggers.prop('disabled', !on);
    }

    // Closing the modal before answering shouldn't strand the user with a disabled
    // wheel — re-enable SPIN so they can resume today's (same) question. If the
    // result is already showing, the day is done, so leave the completed state.
    if (modalEl) {
        $(modalEl).on('hidden.bs.modal', function () {
            if (!$('#dsResultStep').hasClass('d-none')) return;
            setStatusPill('avail', '🔥 <span>Daily spin available</span>');
            enableSpin(true);
        });
    }

    /* ---------------- initial state ---------------- */
    function loadStatus() {
        $.getJSON(URLS.status).done(function (res) {
            if (!res || !res.success) { spinError(); return; }
            if (res.state === 'completed') {
                renderCompleted(res);
            } else {
                setStatusPill('avail', '🔥 <span>Daily spin available</span>');
                enableSpin(true);
            }
        }).fail(spinError);
    }
    function spinError() {
        setStatusPill('avail', '⚠️ <span>Couldn\'t load your spin. Refresh to try again.</span>');
        enableSpin(false);
    }
    function renderCompleted(res) {
        var score = (res && res.correct_count != null && res.total) ? (' — ' + res.correct_count + '/' + res.total + ' correct') : '';
        setStatusPill('done', '✓ <span>You already played today' + score + '</span>');
        enableSpin(false);
        $triggers.filter('button').find('.ds-spin-label').text('Come back tomorrow');
        $done.removeClass('d-none');
    }

    /* ---------------- spinning ---------------- */
    function targetRotationFor(index) {
        // Bring wedge `index` (top-anchored, centre = index*seg + seg/2) under the
        // top pointer, always spinning forward at least 5 full turns.
        var seg = 360 / SEG_COUNT;
        var targetMod = (360 - (index * seg + seg / 2)) % 360;
        var currentMod = ((rotation % 360) + 360) % 360;
        var delta = (targetMod - currentMod + 360) % 360;
        var spins = reduce ? 0 : 5;
        rotation += spins * 360 + delta;
        return rotation;
    }

    $triggers.on('click', function () {
        if (busy || $(this).prop('disabled')) return;
        busy = true;
        enableSpin(false);
        $hub.addClass('is-spinning');
        setStatusPill('avail', '🎡 <span>Spinning…</span>');

        post(URLS.spin).done(function (res) {
            if (!res || !res.success) { onSpinFail(res); return; }
            if (res.state === 'already_completed') { $hub.removeClass('is-spinning'); busy = false; renderCompleted(res); return; }
            if (res.state !== 'question') { onSpinFail(res); return; }

            current = { spin_id: res.spin_id, total: res.total };
            var deg = targetRotationFor(res.target_segment);
            $rot.css('transform', 'rotate(' + deg + 'deg)');

            var wait = reduce ? 400 : 4300;
            setTimeout(function () {
                $hub.removeClass('is-spinning');
                busy = false;
                openQuestions(res);
            }, wait);
        }).fail(function () { onSpinFail(null); });
    });

    function onSpinFail(res) {
        $hub.removeClass('is-spinning');
        busy = false;
        enableSpin(true);
        setStatusPill('avail', '⚠️ <span>' + ((res && res.message) || 'Spin failed. Please try again.') + '</span>');
    }

    /* ---------------- questions (the full set) ---------------- */
    var $qlist  = $('#dsQuestions');
    var $submit = $('#dsSubmit');
    var $footer = $('#dsFooter');
    var totalQ  = 0;

    function openQuestions(res) {
        $('#dsModalTitle').html('🎉 You landed on “' + $('<i>').text(res.category).html() + '”');
        $('.ds-total').text(res.total);
        $('#dsAnswerError').addClass('d-none').text('');
        totalQ = res.questions.length;

        var keys = ['A', 'B', 'C', 'D', 'E', 'F'];
        $qlist.empty();
        res.questions.forEach(function (q, qi) {
            var $card = $('<div class="ds-qcard"></div>').attr('data-qid', q.id);
            var $top  = $('<div class="ds-qtop"></div>');
            $('<span class="ds-qnum"></span>').text(qi + 1).appendTo($top);
            $('<span class="ds-qtext"></span>').text(q.text).appendTo($top);
            $card.append($top);
            var $opts = $('<div class="ds-options"></div>');
            q.options.forEach(function (opt, oi) {
                var id = 'dsq' + q.id + 'o' + opt.id;
                var $label = $('<label class="ds-opt"></label>').attr('for', id);
                $('<input type="radio">').attr({ id: id, name: 'dsq_' + q.id, value: opt.id }).appendTo($label);
                $('<span class="ds-opt-key"></span>').text(keys[oi] || (oi + 1)).appendTo($label);
                $('<span class="ds-opt-text"></span>').text(opt.text).appendTo($label);
                $opts.append($label);
            });
            $card.append($opts);
            $qlist.append($card);
        });

        $('#dsAnswered').text('0');
        $submit.prop('disabled', true).find('.ds-submit-label').text('Answer all ' + totalQ + ' to submit');
        $('#dsQuestionStep').removeClass('d-none');
        $('#dsResultStep').addClass('d-none');
        $footer.removeClass('d-none');
        var body = document.querySelector('#dsModal .modal-body'); if (body) body.scrollTop = 0;

        var m = getModal(); if (m) m.show();
    }

    // Track selections; highlight the choice, update progress, gate submit.
    $(document).on('change', '#dsQuestions input[type="radio"]', function () {
        var $card = $(this).closest('.ds-qcard');
        $card.find('.ds-opt').removeClass('is-selected');
        $(this).closest('.ds-opt').addClass('is-selected');
        $card.addClass('is-answered');

        var done = $('#dsQuestions .ds-qcard.is-answered').length;
        $('#dsAnswered').text(done);
        if (done >= totalQ) {
            $submit.prop('disabled', false).find('.ds-submit-label').text('Submit Answers');
        }
    });

    /* ---------------- submit the whole set ---------------- */
    $submit.on('click', function () {
        if (busy) return;
        var answers = {};
        $('#dsQuestions .ds-qcard').each(function () {
            var qid = $(this).attr('data-qid');
            var val = $(this).find('input[type="radio"]:checked').val();
            if (val) answers[qid] = val;
        });
        if (Object.keys(answers).length < totalQ) {
            $('#dsAnswerError').removeClass('d-none').text('Please answer all ' + totalQ + ' questions first.');
            return;
        }
        busy = true;
        var $btn = $(this).prop('disabled', true);
        $btn.find('.ds-submit-label').text('Submitting…');
        $('#dsAnswerError').addClass('d-none');

        post(URLS.answer, { spin_id: current.spin_id, answers: answers }).done(function (res) {
            busy = false;
            if (!res || !res.success) {
                $btn.prop('disabled', false).find('.ds-submit-label').text('Submit Answers');
                $('#dsAnswerError').removeClass('d-none').text((res && res.message) || 'Could not submit. Try again.');
                return;
            }
            showResult(res);
        }).fail(function (xhr) {
            busy = false;
            $btn.prop('disabled', false).find('.ds-submit-label').text('Submit Answers');
            var msg = 'Network error. Please try again.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            $('#dsAnswerError').removeClass('d-none').text(msg);
        });
    });

    function showResult(res) {
        var perfect = res.correct_count === res.total;
        var pass    = res.correct_count >= Math.ceil(res.total / 2);
        $('#dsResultIcon').text(perfect ? '🏆' : (pass ? '🎉' : '💪'));
        $('#dsResultTitle').text(perfect ? 'Perfect Score!' : (pass ? 'Well Played!' : 'Keep Practising!'));
        $('#dsScoreLine').text('You got ' + res.correct_count + ' of ' + res.total + ' correct');

        if (res.xp_earned > 0) {
            $('#dsXpAmount').text(res.xp_earned);
            $('#dsXpGain').removeClass('d-none');
        } else {
            $('#dsXpGain').addClass('d-none');
        }

        // Per-question review.
        var $rev = $('#dsReview').empty();
        (res.review || []).forEach(function (r, i) {
            var $item = $('<div class="ds-review-item"></div>').addClass(r.is_correct ? 'is-correct' : 'is-wrong');
            var $q = $('<div class="ds-review-q"></div>');
            $('<span class="ds-review-mark"></span>').text((r.is_correct ? '✓' : '✗') + ' ' + (i + 1) + '.').appendTo($q);
            $('<span></span>').text(r.question_text).appendTo($q);
            $item.append($q);
            if (!r.is_correct && r.correct_option_text) {
                $('<div class="ds-review-ans">Correct answer: <span class="ok"></span></div>')
                    .find('.ok').text(r.correct_option_text).end().appendTo($item);
            }
            if (r.explanation) {
                $('<div class="ds-review-exp"></div>').text(r.explanation).appendTo($item);
            }
            $rev.append($item);
        });

        // Live XP / level, count-up on total.
        $('#dsLevel').text(res.level != null ? res.level : '—');
        countUp($('#dsTotalXp'), res.total_xp || 0);

        $('#dsQuestionStep').addClass('d-none');
        $('#dsResultStep').removeClass('d-none');
        $footer.addClass('d-none');
        var body = document.querySelector('#dsModal .modal-body'); if (body) body.scrollTop = 0;

        if (res.correct_count > 0 && !reduce) confetti();

        // Day consumed — reflect behind the modal.
        renderCompleted(res);
    }

    function countUp($el, to) {
        if (reduce) { $el.text(numberFmt(to)); return; }
        var from = 0, start = null, dur = 900;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            $el.text(numberFmt(Math.floor(from + (to - from) * p)));
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    function numberFmt(n) { return (n || 0).toLocaleString('en-IN'); }

    /* ---------------- confetti (no dependencies) ---------------- */
    function confetti() {
        var host = document.getElementById('dsConfetti');
        if (!host) return;
        var colors = ['#7C3AED', '#059669', '#2563EB', '#DB2777', '#EA580C', '#FDCB6E'];
        for (var i = 0; i < 90; i++) {
            var p = document.createElement('div');
            p.className = 'ds-confetti-piece';
            p.style.left = Math.random() * 100 + 'vw';
            p.style.background = colors[i % colors.length];
            var dur = 2.4 + Math.random() * 1.8;
            p.style.animation = 'dsFall ' + dur + 's cubic-bezier(.2,.6,.3,1) forwards';
            p.style.transform = 'translateY(0) rotate(' + (Math.random() * 360) + 'deg)';
            host.appendChild(p);
            (function (el, d) { setTimeout(function () { el.remove(); }, d * 1000 + 200); })(p, dur);
        }
    }

    /* ---------------- go ---------------- */
    loadStatus();
})();
</script>
@endpush
