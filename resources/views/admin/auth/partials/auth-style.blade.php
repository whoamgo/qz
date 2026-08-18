{{-- Shared scoped styling for the admin auth screens (UI only). Used by
     login, forgot-password (email), reset and verify-code pages so the whole
     flow shares one visual language. All rules are scoped under .qz-login. --}}
<style>
    .qz-login {
        --qz-indigo: #4f46e5;
        --qz-violet: #7c3aed;
        --qz-ink: #0f172a;
        --qz-muted: #64748b;
        --qz-border: #e2e8f0;
        --qz-ring: rgba(99, 102, 241, .25);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: radial-gradient(1200px 600px at 15% -10%, #312e81 0%, transparent 55%),
                    radial-gradient(1000px 700px at 110% 120%, #6d28d9 0%, transparent 50%),
                    linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }

    .qz-login-card {
        width: 100%;
        max-width: 980px;
        display: grid;
        grid-template-columns: 1.05fr 1fr;
        background: #fff;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 30px 80px -20px rgba(2, 6, 23, .55);
    }

    /* ---- Brand / visual side ---- */
    .qz-login-brand {
        position: relative;
        padding: 48px 42px;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background:
            linear-gradient(160deg, rgba(79, 70, 229, .92) 0%, rgba(124, 58, 237, .92) 100%),
            url('{{ asset('assets/admin/images/login-quiz.png') }}') center/cover no-repeat;
        min-height: 520px;
    }
    .qz-login-brand::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(600px 300px at 100% 0%, rgba(255, 255, 255, .18), transparent 60%);
        pointer-events: none;
    }
    .qz-brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        letter-spacing: .2px;
    }
    .qz-brand-badge img {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .9);
        padding: 6px;
        box-shadow: 0 8px 20px rgba(2, 6, 23, .25);
    }
    .qz-brand-copy h2 {
        font-size: 30px;
        line-height: 1.25;
        font-weight: 700;
        margin: 0 0 12px;
    }
    .qz-brand-copy p {
        margin: 0;
        color: rgba(255, 255, 255, .82);
        font-size: 15px;
    }
    .qz-brand-features {
        list-style: none;
        margin: 26px 0 0;
        padding: 0;
        display: grid;
        gap: 12px;
    }
    .qz-brand-features li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: rgba(255, 255, 255, .92);
    }
    .qz-brand-features i {
        display: grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .18);
        font-size: 12px;
    }

    /* ---- Form side ---- */
    .qz-login-form-wrap {
        padding: 48px 44px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .qz-form-head { margin-bottom: 26px; }
    .qz-form-head h3 {
        margin: 0 0 6px;
        font-size: 24px;
        font-weight: 700;
        color: var(--qz-ink);
    }
    .qz-form-head p {
        margin: 0;
        color: var(--qz-muted);
        font-size: 14px;
    }
    .qz-login .form-group { margin-bottom: 20px; }
    .qz-login label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
    }
    .qz-login .forget-text {
        font-size: 13px;
        font-weight: 500;
        color: var(--qz-indigo);
        text-decoration: none;
    }
    .qz-login .forget-text:hover { text-decoration: underline; }

    .qz-field { position: relative; }
    .qz-field .qz-field-icon {
        position: absolute;
        top: 50%;
        left: 16px;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 16px;
        pointer-events: none;
    }
    .qz-login .form-control {
        width: 100%;
        height: 52px;
        border: 1.5px solid var(--qz-border);
        border-radius: 13px;
        padding: 0 46px;
        font-size: 15px;
        color: var(--qz-ink);
        background: #f8fafc;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .qz-login .form-control:focus {
        outline: none;
        border-color: var(--qz-indigo);
        background: #fff;
        box-shadow: 0 0 0 4px var(--qz-ring);
    }
    .qz-login .form-control::placeholder { color: #a3adba; }

    .qz-pw-toggle {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        color: #94a3b8;
        cursor: pointer;
        padding: 8px;
        line-height: 1;
        border-radius: 8px;
    }
    .qz-pw-toggle:hover { color: var(--qz-indigo); }

    .qz-login .btn-submit {
        width: 100%;
        height: 52px;
        border: 0;
        border-radius: 13px;
        color: #fff;
        font-weight: 600;
        font-size: 15px;
        letter-spacing: .4px;
        cursor: pointer;
        background: linear-gradient(135deg, var(--qz-indigo), var(--qz-violet));
        box-shadow: 0 14px 26px -10px rgba(79, 70, 229, .7);
        transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    }
    .qz-login .btn-submit:hover { transform: translateY(-1px); filter: brightness(1.05); }
    .qz-login .btn-submit:active { transform: translateY(0); }

    .qz-back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 18px;
        font-size: 14px;
        font-weight: 500;
        color: var(--qz-muted);
        text-decoration: none;
    }
    .qz-back-link:hover { color: var(--qz-indigo); }

    /* Keep the captcha widgets tidy inside the new form */
    .qz-login-form-wrap .form-group .mb-2,
    .qz-login-form-wrap .mb-3 { margin-bottom: 18px; }

    @media (max-width: 860px) {
        .qz-login-card { grid-template-columns: 1fr; max-width: 460px; }
        .qz-login-brand { display: none; }
        .qz-login-form-wrap { padding: 38px 30px; }
    }
</style>
