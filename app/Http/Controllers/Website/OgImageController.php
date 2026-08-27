<?php

namespace App\Http\Controllers\Website;

use App\Models\Category;
use App\Models\Quiz;

/**
 * Generates a branded 1200×630 Open Graph card per quiz, so a shared quiz link
 * on WhatsApp / Facebook / X shows the quiz title on a Quiz Mitra card instead
 * of a generic logo. Rendered with GD (no external service), cached to disk and
 * regenerated only when the quiz changes (the update timestamp is in the
 * filename, so a fresh URL busts the crawler cache too).
 */
class OgImageController extends BaseWebsiteController {

    const W = 1200;
    const H = 630;

    /** Branded card for the homepage (shared everywhere without its own image). */
    public function home() {
        $dir = storage_path('app/og');
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $path = $dir . '/home.png';
        if (!is_file($path)) {
            $this->renderCard('QUIZMITRA', 'Free Quizzes for GK, Current Affairs & Competitive Exams',
                'Practice · Earn XP · Compete   ·   quizmitra.com', $path);
        }
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    /** Branded card for a category page (title = "<Category> Quizzes"). */
    public function category($slug) {
        $cat = Category::where('slug', $slug)->where('status', 1)->firstOrFail();
        $dir = storage_path('app/og');
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $path = $dir . '/cat-' . $cat->id . '-' . optional($cat->updated_at)->timestamp . '.png';
        if (!is_file($path)) {
            $this->renderCard(strtoupper($cat->parent_id ? 'Topic' : 'Category'),
                $cat->name . ' Quizzes & Questions',
                'Free ' . $cat->name . ' practice   ·   quizmitra.com', $path);
        }
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    /** Branded card for an exam-prep hub (name resolved from config/exam_hubs). */
    public function exam($slug) {
        abort_unless(config("exam_hubs.hubs.$slug"), 404);
        $name = config("exam_hubs.hubs.$slug.name", ucfirst($slug));
        $dir  = storage_path('app/og');
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $path = $dir . '/exam-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)) . '.png';
        if (!is_file($path)) {
            $this->renderCard('EXAM PREP', $name . ' Quizzes & Mock Practice',
                'Free ' . $name . ' preparation   ·   quizmitra.com', $path);
        }
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    public function quiz($slug) {
        $quiz = Quiz::where('slug', $slug)->where('status', Quiz::STATUS_PUBLISHED)
            ->with('category:id,name')->firstOrFail();

        $dir  = storage_path('app/og');
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $path = $dir . '/quiz-' . $quiz->id . '-' . optional($quiz->updated_at)->timestamp . '.png';

        if (!is_file($path)) {
            $this->render($quiz, $path);
        }

        return response()->file($path, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=604800',   // a week
        ]);
    }

    /** Draws the card and writes it to $path. */
    private function render(Quiz $quiz, string $path): void {
        $font = base_path('assets/font/solaimanLipi_bold.ttf');
        $img  = imagecreatetruecolor(self::W, self::H);

        // Palette
        $navy   = imagecolorallocate($img, 0, 33, 71);      // #002147
        $navy2  = imagecolorallocate($img, 8, 26, 54);
        $white  = imagecolorallocate($img, 255, 255, 255);
        $blue   = imagecolorallocate($img, 96, 165, 250);   // accent
        $muted  = imagecolorallocate($img, 148, 163, 184);
        $green  = imagecolorallocate($img, 34, 197, 94);

        // Background: vertical navy gradient.
        for ($y = 0; $y < self::H; $y++) {
            $t = $y / self::H;
            $c = imagecolorallocate($img,
                (int) (0 + (8 - 0) * $t),
                (int) (33 + (26 - 33) * $t),
                (int) (71 + (54 - 71) * $t));
            imageline($img, 0, $y, self::W, $y, $c);
        }
        // Accent bars top + bottom.
        imagefilledrectangle($img, 0, 0, self::W, 12, $blue);
        imagefilledrectangle($img, 0, self::H - 12, self::W, self::H, $green);

        // Brand + tagline.
        imagettftext($img, 40, 0, 80, 118, $white, $font, 'Quiz Mitra');
        imagettftext($img, 20, 0, 82, 156, $muted, $font, 'Learn. Play. Compete.');

        // Category (uppercase, accent).
        $cat = strtoupper($quiz->category?->name ?? 'Quiz');
        imagettftext($img, 26, 0, 80, 300, $blue, $font, $cat);

        // Title, wrapped to at most 3 lines.
        $lines = $this->wrap($quiz->title, 52, $font, self::W - 160);
        if (count($lines) > 3) { $lines = array_slice($lines, 0, 3); $lines[2] = rtrim($lines[2]) . '…'; }
        $y = 372;
        foreach ($lines as $line) {
            imagettftext($img, 52, 0, 80, $y, $white, $font, $line);
            $y += 72;
        }

        // Meta line at the bottom.
        $count = $quiz->effectiveQuestionCount($quiz->questions()->count());
        $meta  = $count . ' Questions  ·  ' . ucfirst($quiz->difficulty) . '  ·  quizmitra.com';
        imagettftext($img, 26, 0, 80, self::H - 60, $muted, $font, $meta);

        imagepng($img, $path);
        imagedestroy($img);
    }

    /**
     * Generic branded 1200×630 card (home / category / exam hub). Same visual
     * language as the quiz card, driven by an eyebrow + title + meta line.
     */
    private function renderCard(string $eyebrow, string $title, string $meta, string $path): void {
        $font  = base_path('assets/font/solaimanLipi_bold.ttf');
        $img   = imagecreatetruecolor(self::W, self::H);
        $white = imagecolorallocate($img, 255, 255, 255);
        $blue  = imagecolorallocate($img, 96, 165, 250);
        $muted = imagecolorallocate($img, 148, 163, 184);
        $green = imagecolorallocate($img, 34, 197, 94);

        for ($y = 0; $y < self::H; $y++) {
            $t = $y / self::H;
            $c = imagecolorallocate($img, (int) (0 + (8 - 0) * $t), (int) (33 + (26 - 33) * $t), (int) (71 + (54 - 71) * $t));
            imageline($img, 0, $y, self::W, $y, $c);
        }
        imagefilledrectangle($img, 0, 0, self::W, 12, $blue);
        imagefilledrectangle($img, 0, self::H - 12, self::W, self::H, $green);

        imagettftext($img, 40, 0, 80, 118, $white, $font, 'Quiz Mitra');
        imagettftext($img, 20, 0, 82, 156, $muted, $font, 'Learn. Play. Compete.');
        imagettftext($img, 26, 0, 80, 300, $blue, $font, strtoupper($eyebrow));

        $lines = $this->wrap($title, 52, $font, self::W - 160);
        if (count($lines) > 3) { $lines = array_slice($lines, 0, 3); $lines[2] = rtrim($lines[2]) . '…'; }
        $y = 372;
        foreach ($lines as $line) {
            imagettftext($img, 48, 0, 80, $y, $white, $font, $line);
            $y += 68;
        }

        imagettftext($img, 26, 0, 80, self::H - 60, $muted, $font, $meta);

        imagepng($img, $path);
        imagedestroy($img);
    }

    /** Greedy word-wrap using the real rendered width. */
    private function wrap(string $text, int $size, string $font, int $maxWidth): array {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $line  = '';
        foreach ($words as $w) {
            $test = $line === '' ? $w : $line . ' ' . $w;
            $bb   = imagettfbbox($size, 0, $font, $test);
            if (($bb[2] - $bb[0]) > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $w;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') { $lines[] = $line; }
        return $lines ?: [$text];
    }
}
