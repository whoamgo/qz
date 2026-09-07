{{--
    Shared site-info sections: "How QuizMitra Works" (4-step) + "Who Is QuizMitra For?"
    (audience use-cases). Included on the home, categories, current-affairs and the
    General Knowledge category pages. Markup + CSS live here once (common CSS via the
    @push below) so there is a single source to maintain.
--}}

{{-- How QuizMitra Works — 4-step onboarding --}}
<section class="w-section">
    <div class="container">
        <div class="w-section-head text-center d-block">
            <div>
                <h2>How QuizMitra Works</h2>
                <p>From picking a quiz to climbing the leaderboard — start improving in four simple steps.</p>
            </div>
        </div>
        <div class="hx-steps">
            <div class="hx-step">
                <div class="hx-step-num">01</div>
                <span class="hx-step-icon"><i class="bi bi-collection-fill" aria-hidden="true"></i></span>
                <h3>Pick a Quiz</h3>
                <p>Choose from thousands of quizzes across GK, Current Affairs, Reasoning and every major exam.</p>
            </div>
            <div class="hx-step">
                <div class="hx-step-num">02</div>
                <span class="hx-step-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
                <h3>Answer the Questions</h3>
                <p>Attempt clean, timed multiple-choice questions on any device — no clutter, no distractions.</p>
            </div>
            <div class="hx-step">
                <div class="hx-step-num">03</div>
                <span class="hx-step-icon"><i class="bi bi-clipboard2-data-fill" aria-hidden="true"></i></span>
                <h3>See Instant Results</h3>
                <p>Get your score instantly with the correct answer and a written explanation for every question.</p>
            </div>
            <div class="hx-step">
                <div class="hx-step-num">04</div>
                <span class="hx-step-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                <h3>Earn XP &amp; Improve</h3>
                <p>Collect XP, unlock badges, spot weak topics and climb the leaderboard as you improve.</p>
            </div>
        </div>
    </div>
</section>

{{-- Who is QuizMitra for — audience use-cases --}}
<section class="w-section w-section-alt">
    <div class="container">
        <div class="w-section-head text-center d-block">
            <div>
                <h2>Who Is QuizMitra For?</h2>
                <p>Whatever you're preparing for, there's a focused set of quizzes waiting for you.</p>
            </div>
        </div>
        <div class="hx-use-grid">
            <a href="{{ route('exams') }}" class="hx-use-card">
                <span class="hx-use-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                <span class="hx-use-body"><span class="hx-use-tag">SSC &amp; Railway Aspirants</span>
                    <p>Targeted GK, reasoning and current-affairs practice for CGL, CHSL, NTPC and Group D.</p></span>
                <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a href="{{ route('exams') }}" class="hx-use-card">
                <span class="hx-use-icon"><i class="bi bi-bank2" aria-hidden="true"></i></span>
                <span class="hx-use-body"><span class="hx-use-tag">Banking Aspirants</span>
                    <p>Reasoning, aptitude, banking awareness and current affairs for IBPS &amp; SBI PO/Clerk.</p></span>
                <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a href="{{ route('exams') }}" class="hx-use-card">
                <span class="hx-use-icon"><i class="bi bi-building-fill" aria-hidden="true"></i></span>
                <span class="hx-use-body"><span class="hx-use-tag">UPSC &amp; State PSC</span>
                    <p>Broad General Studies and daily current-affairs practice for Prelims-style testing.</p></span>
                <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a href="{{ route('exams') }}" class="hx-use-card">
                <span class="hx-use-icon"><i class="bi bi-shield-fill-check" aria-hidden="true"></i></span>
                <span class="hx-use-body"><span class="hx-use-tag">Defence Aspirants</span>
                    <p>GK, current affairs and general science practice for NDA, CDS and AFCAT.</p></span>
                <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a href="{{ route('website.categories') }}" class="hx-use-card">
                <span class="hx-use-icon"><i class="bi bi-backpack2-fill" aria-hidden="true"></i></span>
                <span class="hx-use-body"><span class="hx-use-tag">School &amp; College Students</span>
                    <p>Build strong fundamentals in GK, science and more with fun, bite-sized quizzes.</p></span>
                <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a href="{{ route('website.quizzes') }}" class="hx-use-card">
                <span class="hx-use-icon"><i class="bi bi-emoji-laughing-fill" aria-hidden="true"></i></span>
                <span class="hx-use-body"><span class="hx-use-tag">Quiz &amp; Trivia Lovers</span>
                    <p>Sports, movies, entertainment and world trivia — learn something new every day.</p></span>
                <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </a>
        </div>
    </div>
</section>

@push('styles')
<style>
    /* Shared: how-it-works steps + audience use-case cards */
    .hx-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--w-space-4)}
    .hx-step{background:var(--w-bg);border:1.5px solid var(--w-border);border-radius:16px;
        padding:var(--w-space-5);text-align:center;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
    .hx-step:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(2,22,45,.08);border-color:#f6d3b3}
    .hx-step:first-child{border-color:#02162d}
    .hx-step-num{font-size:2rem;font-weight:800;color:#e5e7eb;line-height:1}
    .hx-step:first-child .hx-step-num{color:#02162d}
    .hx-step-icon{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;margin:var(--w-space-3) auto;
        background:#fff2e8;color:#02162d;font-size:1.5rem}
    .hx-step:first-child .hx-step-icon{background:#02162d;color:#fff}
    .hx-step h3{font-size:var(--w-fs-lg);color:var(--w-primary);font-weight:700;margin:0}
    .hx-step h3::after{content:"";display:block;width:34px;height:3px;background:#02162d;border-radius:2px;margin:.55rem auto 0}
    .hx-step p{color:var(--w-muted);font-size:var(--w-fs-sm);margin:.75rem 0 0;line-height:1.6}

    .hx-use-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--w-space-4)}
    .hx-use-card{display:flex;align-items:flex-start;gap:var(--w-space-4);background:var(--w-bg);
        border:1px solid var(--w-border);border-left:4px solid #02162d;border-radius:14px;padding:var(--w-space-5);
        text-decoration:none;transition:transform .18s ease,box-shadow .18s ease}
    .hx-use-card:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(2,22,45,.08)}
    .hx-use-icon{flex:0 0 auto;width:52px;height:52px;border-radius:13px;display:grid;place-items:center;
        background:#fff2e8;color:#02162d;font-size:1.4rem}
    .hx-use-body{flex:1;min-width:0}
    .hx-use-tag{display:block;color:#02162d;font-weight:700;font-size:var(--w-fs-base);margin-bottom:.2rem}
    .hx-use-body p{color:var(--w-muted);font-size:var(--w-fs-sm);margin:0;line-height:1.55}
    .hx-use-arrow{flex:0 0 auto;align-self:center;width:38px;height:38px;border-radius:50%;
        border:1px solid var(--w-border);display:grid;place-items:center;color:#02162d;
        transition:background .15s ease,color .15s ease,transform .15s ease}
    .hx-use-card:hover .hx-use-arrow{background:#02162d;color:#fff;transform:translateX(2px)}

    @media (max-width:991.98px){ .hx-steps{grid-template-columns:repeat(2,1fr)} }
    @media (max-width:767.98px){ .hx-use-grid{grid-template-columns:1fr} }
    @media (max-width:575.98px){ .hx-steps{grid-template-columns:1fr} }
</style>
@endpush
