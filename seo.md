# QuizMitra — SEO System Documentation

Complete reference for the SEO work implemented on the QuizMitra Laravel application.
The **admin panel is the single source of truth** for on-page SEO — titles,
descriptions, H1, content, canonical, robots, Open Graph, Twitter and structured
data are all database-driven, with sensible generated fallbacks when left blank.

- **Canonical domain:** `https://quizmitra.com` (non-www)
- **Public site:** `App\Http\Controllers\Website\*`, routes in `routes/website.php`, views in `resources/views/website/`, layout `resources/views/website/layouts/app.blade.php`
- **SEO service:** `App\Services\SeoService`
- **Editor:** the project's existing **nicEdit** HTML editor (no second editor introduced)
- **HTML sanitisation:** `ezyang/htmlpurifier` (already a dependency)

---

## 1. Contents

1. Technical SEO foundation
2. Dynamic SEO architecture
3. Database changes
4. Per-entity SEO (categories, sub-categories, quizzes, blog, exams, current affairs)
5. Admin tools (per-entity editors, SEO Manager, bulk, command)
6. Structured data
7. Fallback logic & rules
8. Files created / modified
9. Routes added
10. Testing performed
11. Deploy & cache
12. Cautions & data notes
13. Not done / recommendations

---

## 2. Technical SEO foundation

These were fixed/built early and underpin everything else.

| Area | Implementation |
|---|---|
| **Canonical host** | Derived from `config('app.url')`. Layout forces `<link rel="canonical">` and `og:url` to the non-www host. **Production `.env` must be `APP_URL=https://quizmitra.com`.** |
| **www → non-www 301** | Two layers: Apache `.htaccess` rule, and `App\Http\Middleware\CanonicalHost` (web group, GET/HEAD only so payment IPN POSTs are safe; covers Nginx). No redirect loops. |
| **Sitemap** | Dynamic `Website\SitemapController@index` → `/sitemap.xml`. Host-normalised; includes home, quizzes, categories & sub-categories **that hold published quizzes**, published quizzes, blog, exams, mock-tests, pyq, current-affairs, static pages. Excludes admin/auth/profile/attempt/result and thin/empty taxonomy. Cached (`config/seo.php` → `sitemap_cache_ttl`). |
| **robots.txt** | Dynamic `SitemapController@robots` — disallows `/admin`, `/user/login`, `/user/register`, `/password`, `/profile`, `/quiz/attempt|result|review`, `/rooms`, `/ticket`, `/search`; declares the sitemap. |
| **llms.txt** | `SitemapController@llms` → `/llms.txt`. |
| **Crawlable sample questions** | `App\Services\QuizSampleService::forQuiz()` renders ~12 real questions (with answers + explanations) on each quiz page via `<x-website::quiz-samples>` so Google can crawl quiz content without login. Config `config/seo.php → sample_questions`. The real timed attempt never ships answer keys to the browser. |
| **Thin-page protection** | Categories/sub-categories with 0 published quizzes (and no SEO content) render `noindex, follow` and are excluded from the sitemap. |
| **Title de-duplication** | Layout appends the brand suffix (` | {site_name}`) **only if the title doesn't already contain the brand** (spaces ignored) — prevents double-branding like "… | Quiz Mitra | QuizMitra". |
| **Homepage** | Title shortened to ≤60 chars (single brand); all homepage images given `alt` text. |
| **Other fixes** | FAQ page migrated to the new website layout (real CMS content); `/pricing` retired (404); 404 / 419 / 500 error pages rebranded to the site design; extra FAQ entries added. |

---

## 3. Dynamic SEO architecture

**`App\Services\SeoService`** is the central resolver. It converts an entity's
admin-entered fields into the meta payload the layout consumes, filling any blank
field with a generated fallback. **Admin values always win.**

Key methods:

| Method | Purpose |
|---|---|
| `categoryMeta(Category, ctx)` | meta payload for a category / sub-category (title, description, keywords, canonical, robots, image, og_*, twitter_*, custom schema) |
| `categoryContent(Category, ctx)` | on-page `['h1','intro','content','bottom']`, purified |
| `quizMeta(Quiz, ctx)` / `quizContent(Quiz)` | same for quizzes |
| `fillCategoryDefaults()` / `fillQuizDefaults()` | fill **blank** fields only (never overwrite) — shared by the command and the admin bulk generator |
| `robots(model, quizCount)` | effective robots string incl. thin-page guard |
| `customSchema(model)` | decodes admin `schema_json` (ignored if invalid) |
| `purify(html)` | HTMLPurifier sanitisation (see §7) |
| `score(model, count)` | advisory 0–100 SEO score for the admin panel |

**`BaseWebsiteController::seo()`** builds the array rendered by
`layouts/app.blade.php`. It supports per-page `og_title / og_description /
twitter_title / twitter_description` overrides (default to the page
title/description), so social tags are individually controllable.

Controllers call `SeoService`, merge automatic schema (FAQ + Breadcrumb, plus
Quiz schema on quiz pages), and pass `$seoContent` to the view, which renders the
H1, intro and sanitised HTML content blocks.

---

## 4. Database changes

All migrations are **additive, guarded (`hasColumn`), reversible, and leave
existing rows untouched** (every column nullable/defaulted → blank = live fallback).

| Migration | Table | Columns added |
|---|---|---|
| `2026_08_15_100000_add_seo_to_categories_table.php` | `categories` | `meta_title`, `meta_description`, `meta_keywords` *(pre-existing baseline)* |
| `2026_08_23_100000_add_seo_content_to_categories_table.php` | `categories` | `seo_h1`, `seo_intro`, `seo_content` (LONGTEXT), `seo_bottom_content` (LONGTEXT), `canonical_url`, `og_title`, `og_description`, `og_image`, `twitter_title`, `twitter_description`, `robots_index` (bool, default 1), `robots_follow` (bool, default 1), `schema_json` (LONGTEXT), `seo_score`, `seo_updated_at` |
| `2026_08_24_100000_add_seo_to_quizzes_table.php` | `quizzes` | `meta_title`, `meta_description`, `meta_keywords`, `seo_h1`, `seo_intro`, `seo_content`, `canonical_url`, `og_title`, `og_description`, `og_image`, `twitter_title`, `twitter_description`, `robots_index`, `robots_follow`, `schema_json`, `seo_score`, `seo_updated_at` |

> Sub-categories share the `categories` table (`parent_id`), so they use the same
> columns. **Blog needed no migration** — blog SEO lives in the pre-existing
> `frontends.seo_content` JSON column.

---

## 5. Per-entity SEO coverage

Everything below is admin-editable and reflects on the frontend immediately (no code edits).

### Categories & Sub-categories — `/category/{slug}`, `/category/{parent}/{child}`
Title, meta description, keywords, H1, intro, **main HTML content**, **bottom HTML
content**, canonical, robots (index/follow + thin-page guard), OG, Twitter, custom
schema, advisory score. Controller: `Website\CategoryController` (`show`, `subCategory`).

### Quizzes — `/quiz/{slug}`
Title, meta description, keywords, H1, intro, HTML content, canonical, robots, OG,
Twitter, custom schema. Automatic **Quiz + FAQ + Breadcrumb** JSON-LD and crawlable
sample questions remain. Controller: `Website\QuizController@show`.

### Blog — `/blog/{slug}`
Managed via the **existing Frontend Manager → SEO** editor
(`frontends.seo_content`: `description`, `social_title`, `social_description`,
`keywords`, `meta_robots`, `image`). `Website\BlogController@show` consumes all of
it — meta description, keywords, robots, SEO image, and OG/Twitter from
`social_title`/`social_description` — with fallbacks to the post title/body.

### Exam pages — `/exams/{slug}`, `/mock-tests`, `/pyq`
Exam pages **are top-level categories**, so category SEO flows to them
automatically. `Website\ExamController@show` uses `SeoService`; curated
exam-specific title/H1 ("… Preparation") are the fallback when the category's SEO
is blank. Renders admin SEO content. *(mock-tests/pyq hubs keep curated SEO.)*

### Current Affairs — `/current-affairs` (+ `/today`, `/weekly`, `/monthly`)
Hub is the `current-affairs` category → admin SEO applies (`Website\CurrentAffairsController@index`).
**`/current-affairs/today` has dynamic dated SEO** — the title/description include
`now()->format('d M Y')` and refresh **every day automatically** (e.g.
"Daily Current Affairs Quiz – 21 Aug 2026 | Today's GK Questions").

### Dynamic counts
Quiz/question counts on category & exam pages come from the database
(`withCount` / grouped queries) — never hardcoded; they update automatically.

---

## 6. Admin tools

### Per-entity SEO editors
- **Category / Sub-category:** list row → **SEO** button → `admin.category.seo`
  (view `resources/views/admin/category/seo.blade.php`).
- **Quiz:** list row dropdown → **SEO** → `admin.quiz.seo`
  (view `resources/views/admin/quiz/seo.blade.php`).

Each editor has: **Search Preview** (live Google-style), SEO basics with **live
character counters** (title 50–60, description 140–160 warnings), H1, keywords,
canonical, **index/follow** toggles (+ thin-page warning), **SEO Content** (intro +
nicEdit HTML), **Social** (OG/Twitter), **Schema JSON** (soft-validated), and the
**advisory score /100**. Validation warns, never blocks.

### SEO Manager — `admin.seo.manager.dashboard` (`/admin/seo-manager`)
Sidebar → **SEO Manager**. Advisory, database-driven:
- **Cards:** Indexable Pages, Categories, Sub-categories, Quizzes, Blog Posts,
  Using Auto Title, Using Auto Description, **Duplicate Titles**, **Duplicate
  Descriptions**, Noindex Pages.
- **Duplicate tables:** exact stored titles/descriptions used by more than one entity.
- **Quick actions:** open the Bulk Editor per entity; one-click **Generate missing**.

### Bulk SEO Editor — `admin.seo.manager.bulk`
Filter by **Categories / Sub-categories / Quizzes / Blog**, search, **"Only missing
SEO"**, paginated. Each row deep-links to the right editor (blog → Frontend Manager
SEO). Per-tab **Generate missing** button.

### Command — `php artisan seo:generate-defaults [--dry-run]`
Fills **only blank** `meta_title / meta_description / seo_h1` for categories &
sub-categories using the fallback generators. **Never overwrites** admin values.
Also scheduled-safe. *(See the caution in §12 about frozen counts — prefer leaving
fields blank for live counts.)*

---

## 7. Structured data (JSON-LD)

Automatic, per page, valid JSON, non-www URLs:
- **Quiz pages:** `Quiz` (with `hasPart` = the visible sample questions,
  `eduQuestionType`/`acceptedAnswer`/`suggestedAnswer`) + `FAQPage` + `BreadcrumbList`.
- **Category / sub-category / exam pages:** `FAQPage` + `BreadcrumbList`.
- **Blog:** `Article` + `BreadcrumbList`.
- **Home:** `WebSite` + `Organization` + `FAQPage`.
- **Custom:** any valid `schema_json` entered in an editor is emitted **in addition**
  (invalid JSON is silently ignored, never fatal).

Breadcrumb structured data always matches the visible breadcrumb trail.

---

## 8. Fallback logic & rules

- **Priority:** admin value → generated fallback. Empty admin field = live,
  count-accurate fallback (so counts stay current).
- **Title fallbacks:** category → `"{Name} Quiz & Practice Questions"`; sub-category
  → `"{Name} Questions & Quiz – {Parent} Practice"`; quiz →
  `"{Title} — {Category} Practice Test"`. The layout appends ` | {site_name}`
  (once).
- **H1 fallbacks:** category `"{Name} Quizzes"`, sub-category `"{Name} Questions"`,
  quiz `"{Title}"`, exam `"{Name} Preparation"`, current-affairs `"Current Affairs"`.
- **Robots:** `robots_index && (has quizzes || has SEO content)` → `index`, else
  `noindex`; `robots_follow` → `follow`/`nofollow`.
- **HTML content** is **HTMLPurifier-sanitised** before output — allowed tags:
  headings (h2–h4), p, lists, `strong/b/em/i/u`, links (safe hrefs only, `_blank`),
  blockquote, hr, span, div, tables, img. Scripts, `on*` handlers and
  `javascript:` URIs are stripped. *(HTML5 `figure/figcaption` are not enabled.)*
- **Never** hardcodes counts, generates fake content, or creates thin/empty pages.

---

## 9. Files created / modified

**Created**
```
config/seo.php                                    (sample-question knobs, sitemap TTL)
app/Services/SeoService.php
app/Services/QuizSampleService.php
app/Http/Middleware/CanonicalHost.php
app/Http/Controllers/Admin/SeoController.php
app/Console/Commands/GenerateSeoDefaults.php
database/migrations/2026_08_23_100000_add_seo_content_to_categories_table.php
database/migrations/2026_08_24_100000_add_seo_to_quizzes_table.php
resources/views/admin/category/seo.blade.php
resources/views/admin/quiz/seo.blade.php
resources/views/admin/seo/dashboard.blade.php
resources/views/admin/seo/bulk.blade.php
resources/views/website/components/quiz-samples.blade.php
```

**Modified (SEO-relevant)**
```
app/Models/Category.php, app/Models/Quiz.php               (SEO fillable + casts)
app/Http/Controllers/Website/BaseWebsiteController.php     (og/twitter overrides in seo())
app/Http/Controllers/Website/CategoryController.php        (SeoService)
app/Http/Controllers/Website/QuizController.php            (SeoService + sample questions)
app/Http/Controllers/Website/BlogController.php            (consume seo_content)
app/Http/Controllers/Website/ExamController.php            (SeoService)
app/Http/Controllers/Website/CurrentAffairsController.php  (SeoService + dated today)
app/Http/Controllers/Website/SitemapController.php         (robots/sitemap hardening)
app/Http/Controllers/Admin/CategoryController.php          (seo/seoUpdate)
app/Http/Controllers/Admin/QuizController.php              (seo/seoUpdate)
resources/views/website/layouts/app.blade.php             (og/twitter + brand-suffix fix)
resources/views/website/categories/show.blade.php, subcategory.blade.php
resources/views/website/quizzes/show.blade.php
resources/views/website/exams/show.blade.php
resources/views/website/current-affairs/index.blade.php
resources/views/admin/category/{index,sub_index,all_index}.blade.php  (SEO buttons)
resources/views/admin/quiz/index.blade.php                (SEO menu item)
resources/views/admin/partials/sidenav.json               (SEO Manager menu)
routes/admin.php, routes/website.php, .htaccess
```

---

## 10. Routes added

**Public:** none added or changed (existing URLs preserved).

**Admin (all behind the `admin` middleware):**
```
admin.category.seo            GET   /admin/category/seo/{id}
admin.category.seo.update     POST  /admin/category/seo/{id}
admin.quiz.seo                GET   /admin/quiz/seo/{id}
admin.quiz.seo.update         POST  /admin/quiz/seo/{id}
admin.seo.manager.dashboard   GET   /admin/seo-manager
admin.seo.manager.bulk        GET   /admin/seo-manager/bulk
admin.seo.manager.generate    POST  /admin/seo-manager/generate
```
> The `seo-manager` prefix avoids colliding with the pre-existing
> `admin.seo` (`/admin/seo` = global SEO settings, `FrontendController@seoEdit`).

---

## 11. Testing performed

- **Overrides:** admin title/description/H1/OG/Twitter/canonical/robots/schema
  verified live on category, quiz, exam and current-affairs pages.
- **Fallbacks:** blank fields produce correct generated title/description/H1.
- **Sanitisation:** `<script>`, `onclick`, `javascript:` hrefs stripped; safe
  tags/links/tables preserved.
- **Structured data:** valid JSON; Quiz `hasPart`, FAQPage, BreadcrumbList,
  custom `schema_json` all coexist.
- **SEO Manager:** dashboard + 4 bulk tabs render; duplicate detection works.
- **Bulk generate / command:** fill-blanks verified to **never overwrite**.
- **Dynamic dated CA:** `/current-affairs/today` title carries the live date.
- **Regressions:** home, quizzes, category, sub-category, quiz, blog, exams,
  mock-tests, pyq, current-affairs all return **200**; brand appears once; one
  canonical each; admin SEO routes return 302. PHP lints clean; all Blade compiles.

---

## 12. Deploy & cache

After deploying:
```bash
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
Confirm production `.env` has `APP_URL=https://quizmitra.com` (drives canonicals).
The nav/footer/sitemap caches clear automatically on category save; otherwise
`php artisan cache:clear`.

---

## 13. Cautions & data notes

- **Owner content is authoritative.** All 260 categories already carry
  hand-written `meta_title` / `meta_description`. Bulk generate / the command
  **only touch blank fields** — never overwrite these.
- **Frozen counts (§36):** the fallback *description* embeds a live question count.
  The frontend recomputes it every request while the field is blank. If you run
  `seo:generate-defaults` / bulk-generate, it **stores** that description and the
  count no longer auto-updates. Prefer leaving descriptions blank for live counts,
  or re-run generation after big content changes.
- **Exam vs category dual URLs:** `/exams/{slug}` and `/category/{slug}` render the
  **same category** at two URLs (each self-canonical by default). To consolidate,
  set the category's `canonical_url` in its SEO editor — both URLs will then point
  there. *(No change made; your call.)*
- **`schema_json`:** invalid JSON is saved (with a warning) but ignored on the
  frontend until fixed.

---

## 14. Not done / recommendations

- **New short exam/topic URLs** (`/ssc-cgl`, `/banking`, `/reasoning/syllogism`,
  etc.) with 301 redirects were **deliberately not built** — you chose to keep and
  optimise the existing `/category/{parent}/{child}` and `/exams/{slug}` URLs
  (zero redirect risk). Revisit only if you want the shorter URL structure.
- **Quiz/blog bulk-generate** is category+quiz only; blog SEO is authored in the
  Frontend Manager.
- **Next options:** a proper blog↔category tagging system for tighter related-article
  linking; a local GeoIP/MaxMind swap is unrelated (that's the Analytics module);
  periodic review of the SEO Manager duplicate report.
