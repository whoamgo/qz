<?php

namespace Tests\Feature\Social;

use App\Models\Social\SocialAccount;
use App\Services\Social\Adapters\TelegramAdapter;
use App\Services\Social\Contracts\VerifiesPublication;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use DateTimeInterface;

/**
 * A scriptable stand-in for a real platform.
 *
 * Extends TelegramAdapter purely so PlatformRegistry resolves it by the same
 * class name; every method that would touch the network is overridden. Tests
 * script the outcome by pushing onto $script, so a single test can express
 * "fail twice with a timeout, then succeed" - which is exactly the sequence the
 * retry logic exists for.
 */
class FakeAdapter extends TelegramAdapter implements VerifiesPublication {

    /** @var array<int,callable|PlatformException|PublishResult> */
    public array $script = [];

    public int $publishCalls = 0;

    /** Returned by findRecentPublication(), if set. */
    public ?PublishResult $existingPublication = null;

    public int $verifyCalls = 0;

    public function isConfigured(): bool {
        return true;
    }

    public function publish(PublishContext $context): PublishResult {
        $this->publishCalls++;

        $step = array_shift($this->script);

        if ($step instanceof PlatformException) {
            throw $step;
        }
        if (is_callable($step)) {
            return $step($context);
        }
        if ($step instanceof PublishResult) {
            return $step;
        }

        return PublishResult::make('fake-' . $this->publishCalls, 'https://example.test/p/' . $this->publishCalls);
    }

    public function findRecentPublication(PublishContext $context, DateTimeInterface $since): ?PublishResult {
        $this->verifyCalls++;
        return $this->existingPublication;
    }

    public function fetchProfile(SocialAccount $account): array {
        return [
            'external_id' => 'fake-account',
            'name'        => 'Fake Channel',
            'username'    => '@fake',
            'followers'   => 1234,
        ];
    }

    public function testConnection(SocialAccount $account): ConnectionResult {
        return ConnectionResult::ok('Connected.', $this->fetchProfile($account));
    }

    public function fetchPostMetrics(SocialAccount $account, \App\Models\Social\SocialPostPlatform $target): ?array {
        return ['views' => 100, 'likes' => 10, 'comments' => 2, 'shares' => 1];
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        return ['followers' => 1234];
    }
}
