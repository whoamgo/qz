# Multilingual (English + Hindi) — Overview & Production Deploy Runbook

QuizMitra serves English at the site root and Hindi under a `/hi` URL prefix.
All Hindi content is **admin/CSV-entered** (no machine translation); anything not
translated falls back to English automatically, so no page is ever blank.

This document is the deploy runbook for the multilingual release. Follow it top to
bottom on production.

---

## 1. What ships in this release

**Architecture (already reviewed & approved):**

- English = base language in the existing columns, served at `/…`.
  Hindi = `_hi` suffixed columns (`title_hi`, `name_hi`, `question_text_hi`, …),
  served under `/hi/…`.
- `config/locale.php` drives everything (`default=en`, `prefix=hi`,
  `supported=[en,hi]`, `content_fallback=true`, `session_paths`).
- `App\Traits\HasTranslations::tr('field')` is the single read accessor
  (Hindi when present, else English).
- `LanguageMiddleware` is **URL-deterministic**: a `/hi` URL is always Hindi, a
  root content URL is always English, and the non-indexed quiz-play / account
  journey follows the visitor's last language via the session. A public content
  URL can never render two languages.
- `/hi` routes mirror only the indexable GET content routes; route names are the
  English names with an `hi.` prefix.
- SEO: each page self-canonicalises, emits reciprocal `hreflang` (en / hi /
  x-default) in `<head>`, and the sitemap lists both language versions with
  `xhtml:link` alternates. `robots.txt` disallows `/hi/search`.

**Admins enter Hindi** via the existing Category / Quiz / Question-Bank editors
(side-by-side English + Hindi fields) **or** by adding the optional `*_hi` columns
to a question-import / quiz-import CSV.

**No JS/CSS asset changes** — this release is PHP + Blade + migrations only, so
**no `npm run build` / Vite step is required.**

---

## 2. Pre-deploy

- [ ] Merge/deploy the release branch to the production host.
- [ ] Back up the database (routine; the schema change is additive but always
      snapshot before a migration).
- [ ] Confirm `APP_URL` is the production root (`https://quizmitra.com`) — the
      `/hi` links, canonicals and hreflang are all built from it.

## 3. Deploy steps (run on the production host)

```bash
# 1. Pull code (however you deploy) then install deps + refresh the optimized autoloader.
composer install --no-dev --optimize-autoloader

# 2. Run the 6 additive Hindi migrations (all nullable, reversible — see §7).
php artisan migrate --force

# 3. Rebuild the framework caches.
php artisan view:cache        # recompile the Blade views (tr()/locale_route/hreflang)
php artisan route:clear       # NOTE: route:cache is unusable app-wide (pre-existing
                              # closure routes in routes/website.php); clear, do NOT cache.

# config:cache — see the callout below. It currently FAILS on a pre-existing bug
# (config/app.php:69). Run it ONLY after that one line is fixed; otherwise skip it
# (the app runs fine reading config from files each boot).
# php artisan config:cache

# 4. Clear application caches so listings rebuild with the new code and the
#    sitemap is regenerated WITH the /hi URLs (it is cached for 6h).
php artisan cache:clear
```

> ⚠️ **Pre-existing bug blocking `config:cache` (not part of this release).**
> `config/app.php:69` reads `'timezone' => $timezone,` but `$timezone` is never
> defined. At runtime this is only a notice → `null` → the framework defaults to
> UTC, so the app works (this is the lone "Notices: 1" in the test output). But
> `php artisan config:cache` runs under strict error handling and **fatals** on it.
> The multilingual config (`config/locale.php`) is a plain array and is
> cache-safe; this bug is unrelated. Two options:
> - **Recommended one-line fix** (preserves the current UTC behaviour and unlocks
>   config caching): change that line to
>   `'timezone' => env('APP_TIMEZONE', 'UTC'),`
> - **Or skip `config:cache`.** The app is fully functional without it — every
>   request just re-parses the config files at boot (a few ms). `tr()`, the
>   middleware and the helpers read `config('locale.*')` from the in-memory
>   repository either way.

> If you prefer not to flush everything in step 4, the **minimum** required is
> `php artisan cache:forget website.sitemap.xml` (so the new `/hi` sitemap is
> served) — but a full `cache:clear` is recommended so every cached listing
> rebuilds with the `name_hi` eager-load fix.

## 4. Post-deploy — SEO / Search Console

- [ ] Open `https://quizmitra.com/sitemap.xml` and confirm it contains `/hi/…`
      `<loc>` entries and `<xhtml:link rel="alternate" hreflang="hi" …>`.
- [ ] Google Search Console → **Sitemaps** → resubmit `sitemap.xml` (same URL;
      it now carries the `/hi` URLs + hreflang).
- [ ] Confirm `https://quizmitra.com/robots.txt` shows `Disallow: /hi/search`
      and still lists the sitemap.
- [ ] Spot-check hreflang with GSC **URL Inspection** on one `/quiz/{slug}` and
      its `/hi/quiz/{slug}` twin (each should report the other as an alternate).

> Note: `/hi` pages are indexed with hreflang even where the Hindi is still an
> English fallback — this is the standard, safe rollout behaviour. As admins add
> Hindi the pages fill in with no further deploy.

## 5. Post-deploy — smoke tests (production)

| Check | Expected |
|---|---|
| `GET /` | English, `<html lang="en">` |
| `GET /hi` | Hindi UI, `<html lang="hi">` |
| `GET /quiz/{slug}` | English; `<link rel="canonical" …/quiz/{slug}>`; hreflang → `/hi/quiz/{slug}` |
| `GET /hi/quiz/{slug}` | Hindi (or English fallback); canonical → `/hi/quiz/{slug}`; reciprocal hreflang |
| Header language switch | Toggles EN ⇄ हिंदी on the **same** page (URL changes to/from `/hi`) |
| Category name on `/hi` listings | Hindi when `name_hi` is set (the Step-14 fix) |
| Start a quiz from a `/hi` page | The timed quiz plays in Hindi |
| Admin: set a Category `name_hi` + a Quiz `title_hi` | Appears on the matching `/hi` page |
| Import a CSV with `*_hi` columns | Hindi promotes to the quiz/question; English-only CSVs still work |

## 6. Regression suite

The multilingual layer is covered by an automated PHPUnit suite. It is safe to run
against production data (all DB-writing tests roll back via `DatabaseTransactions`):

```bash
vendor/bin/phpunit
```

Expected: **48 tests green** (the single Warning/Deprecation/Notice line is a
pre-existing `config/app.php` artifact, unrelated to this work).

## 7. Rollback

- **Code:** revert the release commit and re-run the §3 cache steps.
- **Database:** the six migrations are reversible —
  `php artisan migrate:rollback` removes exactly the `_hi` columns. This is
  **optional**: every `_hi` column is additive and nullable, so leaving them in
  place is harmless even if the code is rolled back (old code never reads them).
  Rolling back the columns **does** discard any Hindi content already entered.

## 8. Files in this release

- **New:** `config/locale.php`, `app/Traits/HasTranslations.php`,
  6 × `database/migrations/*_add_hindi_columns_*`,
  5 × `tests/Feature/*` (HasTranslations, SeoServiceLocale, MultilingualRouting,
  BilingualImport, HindiRendering) + `tests/Unit/.gitkeep`.
- **Changed (core):** `LanguageMiddleware`, `routes/website.php`,
  `app/Http/Helpers/helpers.php` (`locale_route`, `locale_switch_url`,
  `hreflang_alternates`), `SeoService`, `BaseWebsiteController`, `SitemapController`,
  `QuizController`, `QuizAttemptService`, the two import services + their row models
  and admin controllers, the four content models (`Category`, `Quiz`,
  `BankQuestion`, `BankOption`), the admin editors, and the public Blade views
  (header/footer, cards, quiz/category/exam/home/search/current-affairs, quiz-play).

---

### Known limitation (out of scope)

The multiplayer **Rooms** screens (noindex, auth-gated) still show the English
quiz title/category on a Hindi session — Rooms were never part of the frontend
Hindi rendering. Localising them is a self-contained follow-up.
