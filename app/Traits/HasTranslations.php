<?php

namespace App\Traits;

/**
 * Centralised, read-only content-translation accessor.
 *
 * A model lists the base field names that have per-locale translation columns
 * in a `$translatable` property, e.g. ['title', 'description']. The matching
 * column is "<field><suffix>" where the suffix comes from config('locale.suffix')
 * (e.g. 'hi' => '_hi', so title → title_hi).
 *
 * Rules (all driven by config/locale.php and the app's current locale):
 *   • Default locale (en)                    → the base (English) column.
 *   • A field NOT listed in $translatable    → the base column (never guesses a
 *     "_hi" column — explicit whitelist only).
 *   • Requested locale value empty/NULL      → falls back to the base column
 *     (config('locale.content_fallback'), default true).
 *
 * This trait NEVER writes, saves, mutates model attributes, or translates
 * anything automatically. It is a pure read helper.
 */
trait HasTranslations {
    /** Base field names configured for translation on this model. */
    public function translatableFields(): array {
        return property_exists($this, 'translatable') && is_array($this->translatable)
            ? $this->translatable
            : [];
    }

    /**
     * Locale-aware value of a content field, with English fallback.
     *
     * @param  string       $field   base field name (e.g. 'title')
     * @param  string|null  $locale  defaults to the app's current locale
     */
    public function tr(string $field, ?string $locale = null): mixed {
        $base = $this->getAttribute($field);

        // Explicit whitelist: unknown/unconfigured fields keep English behaviour.
        if (!in_array($field, $this->translatableFields(), true)) {
            return $base;
        }

        $locale ??= app()->getLocale();

        // Default language lives in the base column.
        if ($locale === config('locale.default', 'en')) {
            return $base;
        }

        // Locale must have a configured column suffix; otherwise stay English.
        $suffix = config("locale.suffix.$locale");
        if (blank($suffix)) {
            return $base;
        }

        $value = $this->getAttribute($field . $suffix);

        if (filled($value)) {
            return $value;
        }

        // Empty/NULL translation → configurable fallback to English (default on).
        return config('locale.content_fallback', true) ? $base : $value;
    }

    /** True when $field has a non-empty translation for the (current) locale. */
    public function hasTranslation(string $field, ?string $locale = null): bool {
        $locale ??= app()->getLocale();

        if ($locale === config('locale.default', 'en')) {
            return true; // the base value is always the "translation" for default
        }
        if (!in_array($field, $this->translatableFields(), true)) {
            return false;
        }
        $suffix = config("locale.suffix.$locale");
        return filled($suffix) && filled($this->getAttribute($field . $suffix));
    }

    /** True when EVERY translatable field has a translation for the locale. */
    public function translationComplete(?string $locale = null): bool {
        foreach ($this->translatableFields() as $field) {
            if (!$this->hasTranslation($field, $locale)) {
                return false;
            }
        }
        return $this->translatableFields() !== [];
    }
}
