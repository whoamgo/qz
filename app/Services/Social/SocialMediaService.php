<?php

namespace App\Services\Social;

use App\Models\Social\SocialMedia;
use App\Models\Social\SocialSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Upload handling for the media library.
 *
 * Validation is layered, because each layer alone is bypassable:
 *
 *  1. Extension allow-list  - blocks the obvious.
 *  2. Real MIME from finfo  - the browser-supplied type is never trusted.
 *  3. Magic-byte signature  - catches a PHP script renamed to .jpg with a
 *                             spoofed Content-Type.
 *  4. Image re-encode       - images are decoded and re-written, which destroys
 *                             any polyglot payload hiding in the file.
 *
 * Stored filenames are random. The uploaded name is kept only as a display
 * label, so a crafted name can never influence the path on disk.
 */
class SocialMediaService {

    const DISK = 'social';

    /** Extension => the magic bytes a real file of that type starts with. */
    const SIGNATURES = [
        'jpg'  => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png'  => ["\x89PNG\r\n\x1A\n"],
        'gif'  => ['GIF87a', 'GIF89a'],
        'webp' => ['RIFF'],           // 'WEBP' at offset 8, checked separately
        'mp4'  => ['ftyp'],           // at offset 4
        'mov'  => ['ftyp', 'moov'],   // at offset 4
        'webm' => ["\x1A\x45\xDF\xA3"],
        '3gp'  => ['ftyp'],
    ];

    const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm', '3gp'];

    const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp', 'video/x-m4v',
    ];

    /**
     * Validates, stores and catalogues one upload.
     *
     * Re-uploading a file that is already in the library returns the existing
     * record instead of a duplicate - the checksum is computed on the bytes, so
     * a rename does not defeat it.
     *
     * @throws RuntimeException with a message safe to show the admin.
     */
    public function store(UploadedFile $file, array $attributes = []): SocialMedia {
        $this->assertUploadOk($file);

        $extension = $this->safeExtension($file);
        $type      = in_array($extension, self::IMAGE_EXTENSIONS, true) ? 'image' : 'video';

        $this->assertSize($file, $type);
        $this->assertMime($file, $extension);
        $this->assertSignature($file, $extension);

        $checksum = hash_file('sha256', $file->getRealPath());

        if ($existing = SocialMedia::where('checksum', $checksum)->first()) {
            return $existing;
        }

        $this->ensureDirectoryHardened();

        // Random name, date-sharded directory. Nothing from the uploaded
        // filename reaches the filesystem path.
        $filename = Str::random(40) . '.' . $extension;
        $folder   = 'library/' . now()->format('Y/m');
        $path     = $folder . '/' . $filename;

        $disk = Storage::disk(self::DISK);

        if ($type === 'image' && $extension !== 'gif') {
            // Re-encoding strips EXIF (including GPS coordinates) and any
            // appended payload, so what lands on disk is only pixels.
            $this->storeSanitisedImage($file, $path, $extension);
        } else {
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
        }

        $meta = $type === 'image'
            ? $this->probeImage($disk->path($path))
            : $this->probeVideo($disk->path($path));

        $media = SocialMedia::create(array_merge([
            'disk'          => self::DISK,
            'path'          => $path,
            'filename'      => $filename,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'type'          => $type,
            'mime'          => $this->detectMime($disk->path($path)) ?: $file->getMimeType(),
            'extension'     => $extension,
            'size'          => $disk->size($path),
            'checksum'      => $checksum,
            'width'         => $meta['width'] ?? null,
            'height'        => $meta['height'] ?? null,
            'duration'      => $meta['duration'] ?? null,
            'aspect_ratio'  => $meta['aspect_ratio'] ?? null,
            'uploaded_by'   => Auth::guard('admin')->id(),
        ], $attributes));

        $this->generateThumbnail($media);

        SocialAuditLogger::forModel('media.upload', $media, [
            'description' => 'Uploaded ' . $type . ' "' . $media->original_name . '"',
        ]);

        return $media;
    }

    /* ------------------------------------------------------------ Validation */

    protected function assertUploadOk(UploadedFile $file): void {
        if (!$file->isValid()) {
            throw new RuntimeException('The file did not upload correctly (' . $file->getErrorMessage() . ').');
        }
    }

    /**
     * The extension comes from our allow-list, never from the uploaded name.
     * A name like "shell.php.jpg" therefore stores as ".jpg" and a name like
     * "shell.php" is rejected outright.
     */
    protected function safeExtension(UploadedFile $file): string {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed   = array_merge(self::IMAGE_EXTENSIONS, self::VIDEO_EXTENSIONS);

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException(
                'Files of type .' . e($extension) . ' are not accepted. Allowed: ' . implode(', ', $allowed) . '.'
            );
        }

        return $extension;
    }

    protected function assertSize(UploadedFile $file, string $type): void {
        $settings = SocialSetting::config();
        $maxMb    = $type === 'image' ? (int) $settings->max_image_mb : (int) $settings->max_video_mb;
        $maxBytes = $maxMb * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException(
                'The file is ' . convertToReadableSize($file->getSize()) . ', which exceeds the ' . $maxMb . ' MB limit for ' . $type . 's.'
            );
        }
    }

    /** finfo on the actual bytes - the browser's Content-Type is ignored. */
    protected function assertMime(UploadedFile $file, string $extension): void {
        $mime = $this->detectMime($file->getRealPath());

        if (!$mime || !in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException(
                'The file content is "' . e((string) $mime) . '", which is not an accepted image or video type.'
            );
        }

        $expectedPrefix = in_array($extension, self::IMAGE_EXTENSIONS, true) ? 'image/' : 'video/';
        if (!str_starts_with($mime, $expectedPrefix)) {
            throw new RuntimeException('The file extension does not match its actual content.');
        }
    }

    protected function detectMime(string $path): ?string {
        if (!is_readable($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: null;
    }

    /** Confirms the first bytes match a real file of the claimed type. */
    protected function assertSignature(UploadedFile $file, string $extension): void {
        $signatures = self::SIGNATURES[$extension] ?? null;
        if (!$signatures) {
            return;
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $header = fread($handle, 16);
        fclose($handle);

        if ($header === false || $header === '') {
            throw new RuntimeException('The uploaded file is empty.');
        }

        // Container formats put their marker after a 4-byte length field.
        $offset = in_array($extension, ['mp4', 'mov', '3gp'], true) ? 4 : 0;

        foreach ($signatures as $signature) {
            if (str_starts_with(substr($header, $offset), $signature)) {
                if ($extension === 'webp' && substr($header, 8, 4) !== 'WEBP') {
                    continue;
                }
                return;
            }
        }

        throw new RuntimeException(
            'The file does not look like a real .' . e($extension) . ' file. It may be corrupt, or renamed from another format.'
        );
    }

    /* -------------------------------------------------------------- Storage */

    /** Decodes and re-encodes the image, dropping metadata and any payload. */
    protected function storeSanitisedImage(UploadedFile $file, string $path, string $extension): void {
        try {
            $manager = new ImageManager(new Driver());
            $image   = $manager->read($file->getRealPath());

            $encoded = match ($extension) {
                'png'  => $image->toPng(),
                'webp' => $image->toWebp(90),
                default => $image->toJpeg(90),
            };

            Storage::disk(self::DISK)->put($path, (string) $encoded, 'public');
        } catch (\Throwable $e) {
            throw new RuntimeException('The image could not be processed. It may be corrupt or use an unsupported encoding.');
        }
    }

    /**
     * Drops a hardening .htaccess next to the library.
     *
     * The directory has to be web-readable (Instagram fetches by URL), so the
     * defence is to make sure nothing in it can ever be *executed*.
     */
    protected function ensureDirectoryHardened(): void {
        $disk = Storage::disk(self::DISK);

        if ($disk->exists('.htaccess')) {
            return;
        }

        $disk->put('.htaccess', <<<'HTACCESS'
# Social media library - static assets only.
# Nothing here may ever be executed, whatever its extension turns out to be.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py .jsp .asp .sh
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py

<FilesMatch "\.(php|phtml|php[0-9]|phps|cgi|pl|py|jsp|asp|sh|htaccess)$">
    Require all denied
</FilesMatch>

Options -Indexes -ExecCGI
HTACCESS);
    }

    /* --------------------------------------------------------------- Probing */

    protected function probeImage(string $path): array {
        $info = @getimagesize($path);
        if (!$info) {
            return [];
        }

        return [
            'width'        => $info[0],
            'height'       => $info[1],
            'aspect_ratio' => $this->describeRatio($info[0], $info[1]),
        ];
    }

    /**
     * Video dimensions and duration come from ffprobe when it is installed.
     * Without it the fields stay null, and the platform pre-flight checks that
     * depend on them are reported as "could not be verified" rather than
     * silently passing.
     */
    protected function probeVideo(string $path): array {
        $ffprobe = $this->binary('ffprobe');
        if (!$ffprobe) {
            return [];
        }

        $command = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s 2>/dev/null',
            escapeshellcmd($ffprobe),
            escapeshellarg($path)
        );

        $output = @shell_exec($command);
        if (!$output) {
            return [];
        }

        $data   = json_decode($output, true);
        $stream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');

        if (!$stream) {
            return [];
        }

        $width  = (int) ($stream['width'] ?? 0);
        $height = (int) ($stream['height'] ?? 0);

        return [
            'width'        => $width ?: null,
            'height'       => $height ?: null,
            'duration'     => round((float) ($data['format']['duration'] ?? $stream['duration'] ?? 0), 2) ?: null,
            'aspect_ratio' => $width && $height ? $this->describeRatio($width, $height) : null,
        ];
    }

    protected function describeRatio(int $width, int $height): string {
        if (!$width || !$height) {
            return '';
        }

        $ratio = $width / $height;

        // Snap to the ratios platforms actually care about, so the UI can say
        // "9:16" instead of "0.5625".
        $known = ['9:16' => 0.5625, '4:5' => 0.8, '1:1' => 1.0, '4:3' => 1.3333, '16:9' => 1.7778];
        foreach ($known as $label => $value) {
            if (abs($ratio - $value) < 0.02) {
                return $label;
            }
        }

        return round($ratio, 2) . ':1';
    }

    /* ------------------------------------------------------------ Thumbnails */

    public function generateThumbnail(SocialMedia $media): void {
        $disk = Storage::disk($media->disk);
        $path = 'thumbs/' . pathinfo($media->filename, PATHINFO_FILENAME) . '.jpg';

        try {
            if ($media->type === 'image') {
                $manager = new ImageManager(new Driver());
                $image   = $manager->read($disk->path($media->path))->scaleDown(width: 480);
                $disk->put($path, (string) $image->toJpeg(80), 'public');
            } else {
                $ffmpeg = $this->binary('ffmpeg');
                if (!$ffmpeg) {
                    return;
                }

                $target = $disk->path($path);
                @mkdir(dirname($target), 0755, true);

                // A frame one second in, rather than frame zero, which is often
                // black on a fade-in.
                $command = sprintf(
                    '%s -y -ss 00:00:01 -i %s -frames:v 1 -vf scale=480:-1 %s 2>/dev/null',
                    escapeshellcmd($ffmpeg),
                    escapeshellarg($disk->path($media->path)),
                    escapeshellarg($target)
                );
                @shell_exec($command);

                if (!file_exists($target)) {
                    return;
                }
            }

            $media->thumbnail_path = $path;
            $media->save();
        } catch (\Throwable $e) {
            // A missing thumbnail is cosmetic; the asset itself is fine.
        }
    }

    /** Locates an optional binary without trusting $PATH blindly. */
    protected function binary(string $name): ?string {
        static $cache = [];

        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }

        foreach (['/usr/bin/', '/usr/local/bin/', '/opt/homebrew/bin/'] as $dir) {
            if (is_executable($dir . $name)) {
                return $cache[$name] = $dir . $name;
            }
        }

        $which = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
        $which = $which ? trim($which) : '';

        return $cache[$name] = ($which && is_executable($which)) ? $which : null;
    }

    /** True when video probing is available on this server. */
    public function canProbeVideo(): bool {
        return (bool) $this->binary('ffprobe');
    }
}
