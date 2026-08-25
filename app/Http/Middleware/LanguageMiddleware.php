<?php

namespace App\Http\Middleware;

use App\Constants\Status;
use App\Models\Language;
use Closure;

/**
 * Resolves the active locale for every request.
 *
 * URL-first and deterministic for PUBLIC CONTENT (see config/locale.php):
 *   1. A "/<prefix>" URL (e.g. /hi, /hi/quiz/x) is always the translated locale.
 *   2. User-flow / admin paths (config: locale.session_paths) inherit the last
 *      chosen language from the session — so a Hindi visitor keeps Hindi through
 *      the (non-indexed) quiz-play and account journey, which has no /hi mirror.
 *   3. Every other (public content) URL is the default language (English).
 *
 * The chosen language is also mirrored into the session so the flow pages in (2)
 * can follow it. A public content URL therefore never renders two languages.
 */
class LanguageMiddleware
{
    public function handle($request, Closure $next)
    {
        app()->setLocale($this->resolveLocale($request));
        return $next($request);
    }

    /** Deterministic locale for this request. */
    private function resolveLocale($request): string
    {
        $default   = config('locale.default', 'en');
        $prefix    = config('locale.prefix', 'hi');
        $supported = (array) config('locale.supported', [$default]);

        // 1. Translated-locale URL prefix — deterministic, wins over everything.
        if (in_array($prefix, $supported, true) && ($request->is($prefix) || $request->is($prefix . '/*'))) {
            session()->put('lang', $prefix);
            return $prefix;
        }

        // 2. User-flow / admin paths follow the last chosen language.
        foreach ((array) config('locale.session_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                $lang = session('lang', $this->getCode());
                return in_array($lang, $supported, true) ? $lang : $default;
            }
        }

        // 3. Public content at the root is always the default language.
        session()->put('lang', $default);
        return $default;
    }

    public function getCode()
    {
        if (session()->has('lang')) {
            return session('lang');
        }
        $language = Language::where('is_default', Status::ENABLE)->first();
        return $language ? $language->code : 'en';
    }
}
