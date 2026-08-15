<?php

namespace App\Http\Controllers\Website;

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
