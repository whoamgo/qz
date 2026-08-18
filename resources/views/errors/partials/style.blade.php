{{-- Shared styling for the website-themed error pages (404 / 419 / 500).
     Scoped under .w-err and built entirely on the site's own CSS variables so
     every error screen matches the rest of the website. --}}
<style>
    .w-err { padding: clamp(2.5rem, 6vw, 5rem) 0; }
    .w-err-inner { max-width: 640px; margin: 0 auto; text-align: center; }
    .w-err-code {
        font-size: clamp(6rem, 22vw, 11rem);
        font-weight: 800;
        line-height: .9;
        letter-spacing: -.03em;
        background: linear-gradient(135deg, var(--w-primary), #7c3aed);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
        margin-bottom: var(--w-space-3);
    }
    .w-err h1 { font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 700; }
    .w-err-search { display: flex; gap: .5rem; max-width: 460px; margin: 0 auto var(--w-space-6); }
    .w-err-search .form-control { height: 50px; border-radius: 12px; }
    .w-err-links { display: flex; flex-wrap: wrap; gap: .5rem .75rem; justify-content: center; }
    .w-err-links a {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .4rem .9rem; border: 1px solid var(--w-border); border-radius: 999px;
        font-size: .9rem; color: var(--w-body); text-decoration: none;
        transition: border-color .15s ease, color .15s ease;
    }
    .w-err-links a:hover { border-color: var(--w-primary); color: var(--w-primary); }
</style>
