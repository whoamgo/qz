<?php

namespace App\Services\Social;

use App\Models\Social\SocialBulkImport;
use App\Models\Social\SocialBulkImportRow;
use App\Models\Social\SocialCampaign;
use App\Models\Social\SocialMedia;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * CSV bulk scheduling.
 *
 * Deliberately two-phase: parse and validate first, show the admin exactly what
 * will be created, and only import once they confirm. A bulk importer that
 * writes immediately turns one bad column mapping into fifty posts to delete by
 * hand.
 */
class SocialBulkImportService {

    /** Recognised headers. Everything else in the file is ignored. */
    const COLUMNS = [
        'title'        => 'Post title',
        'caption'      => 'Caption / post text',
        'description'  => 'Long description (YouTube)',
        'hashtags'     => 'Hashtags, space or comma separated',
        'cta'          => 'Call to action',
        'quiz_url'     => 'Quiz URL',
        'website_url'  => 'Website URL',
        'platforms'    => 'Platforms, pipe separated (youtube|instagram|x)',
        'scheduled_at' => 'Publish date and time (YYYY-MM-DD HH:MM)',
        'media'        => 'Media library file names or ids, pipe separated',
        'thumbnail'    => 'Thumbnail file name or id',
        'campaign'     => 'Campaign name (created if missing)',
        'language'     => 'Language',
        'content_type' => 'text | image | video | reel | short | link',
    ];

    const REQUIRED = ['caption', 'platforms'];

    public function __construct(
        protected SocialPostService $posts,
        protected PlatformRegistry $registry,
        protected MediaValidator $validator,
    ) {}

    /* ---------------------------------------------------------------- Parse */

    /** Parses and validates an upload, storing a row per record for preview. */
    public function preview(UploadedFile $file, array $options = []): SocialBulkImport {
        $import = SocialBulkImport::create([
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'status'        => 'pending',
            'options'       => $options,
            'created_by'    => Auth::guard('admin')->id(),
        ]);

        $rows = $this->readCsv($file);

        if (!$rows) {
            $import->update(['status' => 'failed', 'error' => 'The file contained no readable rows.']);
            return $import;
        }

        $header = array_map(fn ($h) => Str::snake(trim(strtolower($h))), array_shift($rows));

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            $import->update([
                'status' => 'failed',
                'error'  => 'The file is missing required column(s): ' . implode(', ', $missing) . '.',
            ]);
            return $import;
        }

        $valid = 0;
        $invalid = 0;

        foreach ($rows as $index => $raw) {
            $data   = $this->mapRow($header, $raw);
            $errors = $this->validateRow($data, $options);

            SocialBulkImportRow::create([
                'social_bulk_import_id' => $import->id,
                'row_number'            => $index + 2, // +1 for header, +1 for 1-based
                'data'                  => $data,
                'errors'                => $errors ?: null,
                'status'                => $errors ? 'invalid' : 'valid',
            ]);

            $errors ? $invalid++ : $valid++;
        }

        $import->update([
            'status'       => 'previewed',
            'total_rows'   => $valid + $invalid,
            'valid_rows'   => $valid,
            'invalid_rows' => $invalid,
        ]);

        return $import->fresh();
    }

    /**
     * Creates posts from the rows that validated.
     *
     * Invalid rows are skipped, never guessed at. Rows already imported are
     * skipped too, so re-running a confirmed import is harmless.
     */
    public function process(SocialBulkImport $import, bool $schedule = true): SocialBulkImport {
        $import->update(['status' => 'processing']);

        $created = 0;

        foreach ($import->rows()->where('status', 'valid')->orderBy('row_number')->cursor() as $row) {
            try {
                $post = $this->createFromRow($import, $row);

                $row->update(['status' => 'imported', 'social_post_id' => $post->id]);
                $created++;

                if ($schedule && !empty($row->data['scheduled_at'])) {
                    app(SocialPublisher::class)->dispatch($post, Carbon::parse($row->data['scheduled_at']));
                }
            } catch (\Throwable $e) {
                $row->update([
                    'status' => 'invalid',
                    'errors' => array_merge((array) $row->errors, [$e->getMessage()]),
                ]);
            }
        }

        $import->update([
            'status'        => 'completed',
            'created_posts' => $created,
        ]);

        SocialAuditLogger::forModel('bulk.import', $import, [
            'description' => "Bulk import created $created post(s) from \"{$import->original_name}\"",
        ]);

        return $import->fresh();
    }

    protected function createFromRow(SocialBulkImport $import, SocialBulkImportRow $row) {
        $data      = $row->data;
        $platforms = $this->parsePlatforms($data['platforms'] ?? '');

        $campaignId = null;
        if (!empty($data['campaign'])) {
            $campaign = SocialCampaign::firstOrCreate(
                ['name' => $data['campaign']],
                ['status' => 'active', 'created_by' => Auth::guard('admin')->id()]
            );
            $campaignId = $campaign->id;
        }

        $mediaIds = [
            'primary'   => $this->resolveMedia($data['media'] ?? ''),
            'thumbnail' => $this->resolveMedia($data['thumbnail'] ?? ''),
        ];

        return $this->posts->create([
            'title'              => $data['title'] ?? null,
            'caption'            => $data['caption'] ?? null,
            'description'        => $data['description'] ?? null,
            'hashtags'           => $data['hashtags'] ?? null,
            'cta'                => $data['cta'] ?? null,
            'quiz_url'           => $data['quiz_url'] ?? null,
            'website_url'        => $data['website_url'] ?? null,
            'language'           => $data['language'] ?? 'english',
            'content_type'       => $data['content_type'] ?? null,
            'social_campaign_id' => $campaignId,
            'source_type'        => 'bulk_import',
            'source_id'          => $import->id,
            // Deterministic per import row, so a re-run cannot double up.
            'idempotency_key'    => 'bulk:' . $import->id . ':' . $row->row_number,
        ], $platforms, [], $mediaIds);
    }

    /* ------------------------------------------------------------ Validation */

    protected function validateRow(array $data, array $options): array {
        $errors = [];

        if (empty(trim((string) ($data['caption'] ?? '')))) {
            $errors[] = 'Caption is required.';
        }

        $platforms = $this->parsePlatforms($data['platforms'] ?? '');
        if (!$platforms) {
            $errors[] = 'No recognised platforms. Use pipe-separated keys such as youtube|instagram|facebook|x|linkedin|telegram.';
        }

        foreach ($platforms as $platform) {
            if (!$this->registry->make($platform)->isConfigured()) {
                $errors[] = \App\Constants\SocialStatus::platformName($platform) . ' is not configured on this server.';
            }
        }

        if (!empty($data['scheduled_at'])) {
            try {
                $when = Carbon::parse($data['scheduled_at']);
                if ($when->isPast() && $when->diffInMinutes(now()) > 5) {
                    $errors[] = 'The scheduled time is in the past (' . $when->format('d M Y H:i') . ').';
                }
            } catch (\Throwable $e) {
                $errors[] = 'Could not read the scheduled time "' . e($data['scheduled_at']) . '". Use YYYY-MM-DD HH:MM.';
            }
        }

        foreach (['media', 'thumbnail'] as $field) {
            foreach ($this->splitList($data[$field] ?? '') as $reference) {
                if (!$this->findMedia($reference)) {
                    $errors[] = 'Media "' . e($reference) . '" was not found in the library. Upload it first.';
                }
            }
        }

        // Media-required platforms fail late and confusingly, so catch it here.
        $hasMedia = (bool) $this->splitList($data['media'] ?? '');
        foreach ($platforms as $platform) {
            if (!$hasMedia && PlatformCapability::supports($platform, 'requires_media')) {
                $errors[] = \App\Constants\SocialStatus::platformName($platform) . ' requires an image or video.';
            }
        }

        return $errors;
    }

    /* -------------------------------------------------------------- Helpers */

    protected function readCsv(UploadedFile $file): array {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return [];
        }

        $rows = [];
        // A generous but finite cap - a runaway file should not exhaust memory.
        while (count($rows) < 5000 && ($line = fgetcsv($handle, 0, ',')) !== false) {
            if ($line === [null] || $line === false) {
                continue;
            }
            $rows[] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $line);
        }

        fclose($handle);

        return $rows;
    }

    protected function mapRow(array $header, array $raw): array {
        $data = [];
        foreach ($header as $i => $column) {
            if (!array_key_exists($column, self::COLUMNS)) {
                continue;
            }
            $data[$column] = $raw[$i] ?? null;
        }
        return $data;
    }

    protected function parsePlatforms(string $value): array {
        return collect($this->splitList($value))
            ->map(fn ($p) => strtolower(trim($p)))
            ->map(fn ($p) => match ($p) {
                'twitter', 'x.com' => 'x',
                'fb'               => 'facebook',
                'ig'               => 'instagram',
                'yt'               => 'youtube',
                default            => $p,
            })
            ->filter(fn ($p) => $this->registry->has($p))
            ->unique()
            ->values()
            ->all();
    }

    protected function splitList($value): array {
        return array_values(array_filter(array_map('trim', preg_split('/[|,;]/', (string) $value) ?: [])));
    }

    /** Resolves "media" cell entries to library ids. */
    protected function resolveMedia($value): array {
        return collect($this->splitList($value))
            ->map(fn ($ref) => $this->findMedia($ref)?->id)
            ->filter()
            ->values()
            ->all();
    }

    protected function findMedia(string $reference): ?SocialMedia {
        if (is_numeric($reference)) {
            return SocialMedia::find((int) $reference);
        }

        return SocialMedia::where('original_name', $reference)
            ->orWhere('filename', $reference)
            ->orWhere('title', $reference)
            ->first();
    }

    /** A ready-to-fill CSV template for the download button. */
    public function templateCsv(): string {
        $header  = implode(',', array_keys(self::COLUMNS));
        $example = implode(',', [
            '"Daily GK Quiz - 1 Sept"',
            '"Can you answer this GK question? Test yourself now."',
            '"Full description used by YouTube."',
            '"#gk #quiz #currentaffairs"',
            '"Play now"',
            '"https://quizmitra.com/quiz/daily-gk"',
            '""',
            '"instagram|facebook|telegram"',
            '"2026-09-02 20:00"',
            '"daily-quiz-1.jpg"',
            '""',
            '"Daily Quiz September 2026"',
            '"english"',
            '"image"',
        ]);

        return $header . "\n" . $example . "\n";
    }
}
