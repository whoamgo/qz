<?php

namespace App\Services\Social;

use App\Constants\SocialStatus;
use App\Services\Social\Adapters\FacebookAdapter;
use App\Services\Social\Adapters\InstagramAdapter;
use App\Services\Social\Adapters\LinkedInAdapter;
use App\Services\Social\Adapters\TelegramAdapter;
use App\Services\Social\Adapters\ThreadsAdapter;
use App\Services\Social\Adapters\WhatsAppAdapter;
use App\Services\Social\Adapters\XAdapter;
use App\Services\Social\Adapters\YouTubeAdapter;
use App\Services\Social\Contracts\PlatformAdapter;
use App\Services\Social\Support\PlatformException;

/**
 * Maps a platform key to its adapter.
 *
 * Adding a platform is a one-line change here plus the adapter class - nothing
 * else in the system needs to learn about it.
 */
class PlatformRegistry {

    const ADAPTERS = [
        SocialStatus::YOUTUBE   => YouTubeAdapter::class,
        SocialStatus::INSTAGRAM => InstagramAdapter::class,
        SocialStatus::FACEBOOK  => FacebookAdapter::class,
        SocialStatus::X         => XAdapter::class,
        SocialStatus::LINKEDIN  => LinkedInAdapter::class,
        SocialStatus::TELEGRAM  => TelegramAdapter::class,
        SocialStatus::WHATSAPP  => WhatsAppAdapter::class,
        SocialStatus::THREADS   => ThreadsAdapter::class,
    ];

    /** @var array<string,PlatformAdapter> */
    protected array $resolved = [];

    public function has(string $platform): bool {
        return isset(self::ADAPTERS[$platform]);
    }

    /**
     * @throws PlatformException for an unknown platform - which normally means
     *         a tampered form field rather than a real request.
     */
    public function make(string $platform): PlatformAdapter {
        if (!$this->has($platform)) {
            throw new PlatformException('Unknown social platform: ' . e($platform), 'unknown_platform');
        }

        return $this->resolved[$platform] ??= app(self::ADAPTERS[$platform]);
    }

    /** @return array<string,PlatformAdapter> */
    public function all(): array {
        $out = [];
        foreach (array_keys(self::ADAPTERS) as $platform) {
            $out[$platform] = $this->make($platform);
        }
        return $out;
    }

    /** Platform keys whose server-side credentials are present. */
    public function configured(): array {
        return array_keys(array_filter($this->all(), fn (PlatformAdapter $a) => $a->isConfigured()));
    }

    /** Everything the accounts screen needs to render one platform card. */
    public function describe(string $platform): array {
        $adapter = $this->make($platform);

        return [
            'key'          => $platform,
            'name'         => SocialStatus::platformName($platform),
            'icon'         => SocialStatus::platformIcon($platform),
            'color'        => SocialStatus::platformColor($platform),
            'configured'   => $adapter->isConfigured(),
            'auth'         => config('social.platforms.' . $platform . '.auth', 'oauth2'),
            'capabilities' => PlatformCapability::for($platform),
        ];
    }

    public function describeAll(): array {
        return array_map(fn ($p) => $this->describe($p), array_keys(self::ADAPTERS));
    }
}
