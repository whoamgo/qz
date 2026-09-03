<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A reusable caption/description template.
 *
 * Rendering is deliberately a plain {{ variable }} substitution rather than
 * Blade: templates are admin-editable content, and compiling them as Blade would
 * turn the template editor into arbitrary PHP execution.
 */
class SocialTemplate extends Model {

    protected $table = 'social_templates';

    protected $fillable = [
        'name', 'slug', 'category', 'platform', 'title_template', 'caption_template',
        'description_template', 'hashtag_template', 'cta_template', 'variables',
        'is_active', 'usage_count', 'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    const CATEGORIES = [
        'daily_quiz'       => 'Daily Quiz',
        'current_affairs'  => 'Current Affairs',
        'new_blog'         => 'New Blog',
        'new_video'        => 'New Video',
        'quiz_challenge'   => 'Quiz Challenge',
        'result'           => 'Result Announcement',
        'festival'         => 'Festival Quiz',
        'contest'          => 'Contest',
        'custom'           => 'Custom',
    ];

    /** Variables the engine always provides, shown as chips in the editor. */
    const BUILTIN_VARIABLES = [
        'title', 'caption', 'description', 'quiz_url', 'website_url', 'category',
        'hashtags', 'date', 'time', 'cta', 'site_name', 'question', 'answer', 'language',
    ];

    protected static function booted(): void {
        static::saving(function (self $template) {
            if (!$template->slug) {
                $base = Str::slug($template->name) ?: 'template';
                $slug = $base;
                $i    = 1;
                while (static::where('slug', $slug)->where('id', '!=', $template->id ?? 0)->exists()) {
                    $slug = $base . '-' . (++$i);
                }
                $template->slug = $slug;
            }
            $template->variables = $template->discoverVariables();
        });
    }

    /** Every {{ name }} used anywhere in this template. */
    public function discoverVariables(): array {
        $blob = implode("\n", array_filter([
            $this->title_template, $this->caption_template,
            $this->description_template, $this->hashtag_template, $this->cta_template,
        ]));

        preg_match_all('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', $blob, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Substitutes {{ variables }} in a single string.
     *
     * Unknown variables collapse to an empty string rather than being left as
     * literal braces, so a missing value never ships to a live platform as
     * "{{ quiz_url }}".
     */
    public static function renderString(?string $template, array $data): string {
        if (!$template) {
            return '';
        }

        $rendered = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($m) use ($data) {
            $value = data_get($data, $m[1], '');
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            return is_scalar($value) ? (string) $value : '';
        }, $template);

        // Collapse the blank lines a removed variable leaves behind.
        return trim(preg_replace("/\n{3,}/", "\n\n", $rendered));
    }

    /** Renders every field of the template at once. */
    public function render(array $data): array {
        return [
            'title'       => static::renderString($this->title_template, $data),
            'caption'     => static::renderString($this->caption_template, $data),
            'description' => static::renderString($this->description_template, $data),
            'hashtags'    => static::renderString($this->hashtag_template, $data),
            'cta'         => static::renderString($this->cta_template, $data),
        ];
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function getCategoryNameAttribute(): string {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }
}
