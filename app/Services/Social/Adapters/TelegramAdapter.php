<?php

namespace App\Services\Social\Adapters;

use App\Constants\SocialStatus;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\PlatformCapability;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use App\Services\Social\Support\SocialHttpClient;

/**
 * Telegram Bot API.
 *
 * Telegram has no OAuth: the admin creates a bot with @BotFather, adds it to the
 * channel as an administrator and pastes the bot token plus the target chat id.
 * The token is stored in the account's encrypted credentials like any other.
 *
 * Media is uploaded directly (multipart) rather than by URL, so this adapter
 * works even on a site that is not publicly reachable - which makes it the
 * easiest platform to verify a fresh install with.
 */
class TelegramAdapter extends AbstractPlatformAdapter {

    public function key(): string {
        return SocialStatus::TELEGRAM;
    }

    /**
     * Telegram credentials are supplied per account through the connect form,
     * so the platform is always available to connect; an env-level token merely
     * pre-fills it.
     */
    public function isConfigured(): bool {
        return true;
    }

    protected function endpoint(SocialAccount $account, string $method): string {
        $token = $account->credential('bot_token') ?: $this->config('bot_token');
        if (!$token) {
            throw PlatformException::notConnected($this->key());
        }
        return rtrim((string) $this->config('api_base', 'https://api.telegram.org'), '/') . '/bot' . $token . '/' . $method;
    }

    protected function chatId(SocialAccount $account): string {
        $chatId = $account->credential('chat_id') ?: $this->config('chat_id');
        if (!$chatId) {
            throw new PlatformException(
                'No Telegram chat/channel is configured for this account. Reconnect it and provide the channel id or @username.',
                'not_configured'
            );
        }
        return (string) $chatId;
    }

    /* ---------------------------------------------------------------- Profile */

    public function fetchProfile(SocialAccount $account): array {
        $data = $this->result($this->http->request()->get($this->endpoint($account, 'getMe')));

        if (!($data['ok'] ?? false)) {
            throw new PlatformException('Telegram rejected the bot token.', 'token_expired', false, 401, null, true);
        }

        $bot     = $data['result'] ?? [];
        $profile = [
            'external_id' => (string) ($bot['id'] ?? ''),
            'name'        => $bot['first_name'] ?? 'Telegram Bot',
            'username'    => isset($bot['username']) ? '@' . $bot['username'] : null,
        ];

        // The chat is the thing that actually receives posts, so its title and
        // member count are more useful in the UI than the bot's own name.
        try {
            $chat = $this->result($this->http->request()->get($this->endpoint($account, 'getChat'), [
                'chat_id' => $this->chatId($account),
            ]));
            $info = $chat['result'] ?? [];

            $profile['name']        = $info['title'] ?? $profile['name'];
            $profile['username']    = isset($info['username']) ? '@' . $info['username'] : $profile['username'];
            $profile['profile_url'] = isset($info['username']) ? 'https://t.me/' . $info['username'] : null;

            $count = $this->http->request()->get($this->endpoint($account, 'getChatMemberCount'), [
                'chat_id' => $this->chatId($account),
            ]);
            if ($count->successful()) {
                $profile['followers'] = (int) ($count->json('result') ?? 0);
            }
        } catch (PlatformException $e) {
            // A valid bot token with a bad chat id is still a partial success -
            // surface the bot, and let testConnection report the chat problem.
        }

        return $profile;
    }

    public function testConnection(SocialAccount $account): ConnectionResult {
        try {
            $profile = $this->fetchProfile($account);

            // Posting needs the bot to be an administrator of the channel, and
            // getChat succeeding does not prove that - check it explicitly.
            $me = $this->result($this->http->request()->get($this->endpoint($account, 'getMe')));
            $botId = data_get($me, 'result.id');

            $member = $this->http->request()->get($this->endpoint($account, 'getChatMember'), [
                'chat_id' => $this->chatId($account),
                'user_id' => $botId,
            ]);

            if ($member->successful()) {
                $status = data_get($member->json(), 'result.status');
                if (!in_array($status, ['administrator', 'creator'], true)) {
                    return ConnectionResult::fail(
                        'The bot can see the channel but is not an administrator of it, so it cannot post. '
                        . 'Add the bot as an administrator with "Post Messages" permission.',
                        $profile
                    );
                }
            }

            return ConnectionResult::ok('Connected. The bot is an administrator of the target channel.', $profile);
        } catch (PlatformException $e) {
            return ConnectionResult::fail($e->getMessage());
        } catch (\Throwable $e) {
            return ConnectionResult::fail(SocialHttpClient::classifyThrowable($this->key(), $e)->getMessage());
        }
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $account = $context->account;
        $chatId  = $this->chatId($account);

        $video  = $context->video();
        $images = $context->images();

        // Media captions are capped far lower than plain messages, so the limit
        // depends on whether anything is attached.
        $limit = ($video || $images->isNotEmpty())
            ? PlatformCapability::limit($this->key(), 'media_caption_max', 1024)
            : PlatformCapability::limit($this->key(), 'caption_max', 4096);

        $text = $this->composeBody(
            $this->buildText($context),
            (array) $context->value('hashtags', []),
            $limit
        );

        $params = [
            'chat_id'    => $chatId,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup = $this->buildButtons($context)) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        if ($video) {
            $response = $this->sendFile($account, 'sendVideo', 'video', $video, $params + [
                'caption'            => $text,
                'supports_streaming' => true,
            ]);
        } elseif ($images->count() === 1) {
            $response = $this->sendFile($account, 'sendPhoto', 'photo', $images->first(), $params + [
                'caption' => $text,
            ]);
        } elseif ($images->count() > 1) {
            $response = $this->sendMediaGroup($account, $images, $text, $chatId);
        } else {
            $response = $this->result($this->http->request()->asForm()->post(
                $this->endpoint($account, 'sendMessage'),
                $params + ['text' => $text, 'disable_web_page_preview' => false]
            ));
        }

        if (!($response['ok'] ?? false)) {
            throw new PlatformException(
                'Telegram rejected the message: ' . SocialHttpClient::redactString((string) ($response['description'] ?? 'unknown error')),
                'rejected'
            );
        }

        // sendMediaGroup returns an array of messages; the first is the anchor.
        $message   = $response['result'];
        $message   = isset($message[0]) ? $message[0] : $message;
        $messageId = (string) ($message['message_id'] ?? '');
        $username  = data_get($message, 'chat.username');

        return PublishResult::make(
            $messageId,
            $username ? "https://t.me/{$username}/{$messageId}" : null,
            ['chat_id' => data_get($message, 'chat.id')]
        );
    }

    /** Caption plus the CTA and link, since Telegram renders real links. */
    protected function buildText(PublishContext $context): string {
        $parts = array_filter([
            $context->value('title') ? '<b>' . e($context->value('title')) . '</b>' : null,
            e($context->caption()),
            $context->value('cta'),
        ]);

        $text = implode("\n\n", $parts);

        // The link is added only when no button carries it, to avoid showing
        // the same URL twice.
        if (!$context->value('button_url') && ($link = $context->primaryLink())) {
            $text .= "\n\n" . $link;
        }

        return trim($text);
    }

    /** Optional inline keyboard button ("Play the quiz"). */
    protected function buildButtons(PublishContext $context): ?array {
        $url = $context->value('button_url', $context->primaryLink());
        if (!$url) {
            return null;
        }

        // Telegram only accepts absolute http(s) button URLs.
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        return ['inline_keyboard' => [[[
            'text' => $context->value('button_text') ?: ($context->value('cta') ?: 'Open'),
            'url'  => $url,
        ]]]];
    }

    /** Streams one local file to Telegram as multipart. */
    protected function sendFile(SocialAccount $account, string $method, string $field, $media, array $params): array {
        $path = $media->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The media file could not be read from storage.');
        }

        $request = $this->http->request()->attach($field, fopen($path, 'r'), $media->filename);

        return $this->result($request->post($this->endpoint($account, $method), $params));
    }

    /** Album post: several photos in one message. */
    protected function sendMediaGroup(SocialAccount $account, $images, string $caption, string $chatId): array {
        $request = $this->http->request();
        $group   = [];

        foreach ($images->values() as $i => $image) {
            $path = $image->absolutePath();
            if (!$path || !is_readable($path)) {
                continue;
            }
            $name    = 'file' . $i;
            $request = $request->attach($name, fopen($path, 'r'), $image->filename);

            $entry = ['type' => 'photo', 'media' => 'attach://' . $name];
            if ($i === 0 && $caption !== '') {
                $entry['caption']    = $caption;
                $entry['parse_mode'] = 'HTML';
            }
            $group[] = $entry;
        }

        if (!$group) {
            throw PlatformException::invalidMedia('None of the selected images could be read from storage.');
        }

        return $this->result($request->post($this->endpoint($account, 'sendMediaGroup'), [
            'chat_id' => $chatId,
            'media'   => json_encode($group),
        ]));
    }

    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool {
        if (!$target->platform_post_id) {
            return false;
        }

        $response = $this->http->request()->asForm()->post($this->endpoint($account, 'deleteMessage'), [
            'chat_id'    => $this->chatId($account),
            'message_id' => $target->platform_post_id,
        ]);

        return $response->successful() && (bool) $response->json('ok');
    }

    /**
     * The Bot API exposes no per-post metrics, so nothing is reported rather
     * than inventing numbers. Channel member count is captured by fetchProfile.
     */
    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        return null;
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        try {
            $count = $this->http->request()->get($this->endpoint($account, 'getChatMemberCount'), [
                'chat_id' => $this->chatId($account),
            ]);
            if (!$count->successful()) {
                return null;
            }
            return ['followers' => (int) $count->json('result')];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
