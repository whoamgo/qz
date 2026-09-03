<?php

namespace App\Services\Social\Adapters;

use App\Constants\SocialStatus;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;

/**
 * WhatsApp Business, via the official Cloud API.
 *
 * WhatsApp is messaging, not a feed. "Publishing" here means sending the content
 * to a configured list of recipients, and Meta's policy is explicit: a
 * business-initiated message outside a 24-hour customer service window must use
 * a template that Meta has approved in advance. This adapter therefore sends a
 * template message by default and only falls back to free-form text when the
 * admin has explicitly said the window is open.
 *
 * The credential is a permanent system-user token created in Meta Business
 * Manager; there is no OAuth flow to run here.
 */
class WhatsAppAdapter extends AbstractPlatformAdapter {

    public function key(): string {
        return SocialStatus::WHATSAPP;
    }

    /** Credentials are pasted into the connect form, so it is always offerable. */
    public function isConfigured(): bool {
        return true;
    }

    protected function apiBase(): string {
        return rtrim($this->url((string) $this->config('api_base')), '/');
    }

    protected function phoneNumberId(SocialAccount $account): string {
        $id = $account->credential('phone_number_id') ?: $this->config('phone_number_id');
        if (!$id) {
            throw new PlatformException(
                'No WhatsApp phone number is configured. Reconnect the account and provide the Phone Number ID from Meta Business Manager.',
                'not_configured'
            );
        }
        return (string) $id;
    }

    protected function waToken(SocialAccount $account): string {
        $token = $account->accessToken() ?: $this->config('access_token');
        if (!$token) {
            throw PlatformException::notConnected($this->key());
        }
        return (string) $token;
    }

    /** Where the message goes - set on the account at connect time. */
    protected function recipients(SocialAccount $account, PublishContext $context): array {
        $list = $context->value('recipients') ?: $account->credential('recipients', []);

        if (is_string($list)) {
            $list = preg_split('/[\s,;]+/', $list) ?: [];
        }

        // E.164 without the leading +, which is what the Cloud API expects.
        $list = collect($list)
            ->map(fn ($n) => preg_replace('/\D/', '', (string) $n))
            ->filter(fn ($n) => strlen($n) >= 8)
            ->unique()
            ->values()
            ->all();

        if (!$list) {
            throw new PlatformException(
                'No WhatsApp recipients are configured for this account. Add recipient numbers when connecting the account, or set them on the WhatsApp tab of the post.',
                'no_recipients'
            );
        }

        return $list;
    }

    public function fetchProfile(SocialAccount $account): array {
        $token  = $this->waToken($account);
        $number = $this->result($this->http->withToken($token)->get(
            $this->apiBase() . '/' . $this->phoneNumberId($account),
            ['fields' => 'display_phone_number,verified_name,quality_rating']
        ));

        return [
            'external_id' => $this->phoneNumberId($account),
            'name'        => $number['verified_name'] ?? 'WhatsApp Business',
            'username'    => $number['display_phone_number'] ?? null,
            'meta'        => ['quality_rating' => $number['quality_rating'] ?? null],
        ];
    }

    public function testConnection(SocialAccount $account): ConnectionResult {
        try {
            $profile = $this->fetchProfile($account);
            $count   = count((array) $account->credential('recipients', []));

            return ConnectionResult::ok(
                'Connected as "' . ($profile['name'] ?? '') . '". ' . $count . ' recipient(s) configured.',
                $profile
            );
        } catch (PlatformException $e) {
            return ConnectionResult::fail($e->getMessage());
        }
    }

    /** Approved message templates, for the publisher's template picker. */
    public function fetchTemplates(SocialAccount $account): array {
        $businessId = $account->credential('business_id') ?: $this->config('business_id');
        if (!$businessId) {
            return [];
        }

        try {
            $body = $this->result($this->http->withToken($this->waToken($account))
                ->get($this->apiBase() . '/' . $businessId . '/message_templates', [
                    'fields' => 'name,status,language,category',
                    'limit'  => 100,
                ]));

            return collect($body['data'] ?? [])
                ->where('status', 'APPROVED')
                ->map(fn ($t) => [
                    'name'     => $t['name'] ?? null,
                    'language' => $t['language'] ?? 'en_US',
                    'category' => $t['category'] ?? null,
                ])->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $account    = $context->account;
        $token      = $this->waToken($account);
        $numberId   = $this->phoneNumberId($account);
        $recipients = $this->recipients($account, $context);

        $template = $context->value('template_name') ?: $account->credential('template_name');
        $freeform = (bool) $context->value('allow_freeform', false);

        if (!$template && !$freeform) {
            throw new PlatformException(
                'WhatsApp requires an approved message template for business-initiated messages. '
                . 'Choose a template on the WhatsApp tab, or tick "recipients messaged us in the last 24 hours" to send free-form text.',
                'template_required'
            );
        }

        $mediaId = null;
        if ($media = ($context->video() ?: $context->images()->first())) {
            $mediaId = $this->uploadMedia($token, $numberId, $media);
        }

        $sent    = [];
        $errors  = [];

        foreach ($recipients as $recipient) {
            try {
                $payload = $template
                    ? $this->templatePayload($recipient, $template, $context, $mediaId)
                    : $this->freeformPayload($recipient, $context, $mediaId);

                $response = $this->result(
                    $this->http->withToken($token)->asJson()->post($this->apiBase() . "/$numberId/messages", $payload)
                );

                if ($id = data_get($response, 'messages.0.id')) {
                    $sent[] = $id;
                }
            } catch (PlatformException $e) {
                // One bad number must not stop the broadcast; the failures are
                // reported together at the end.
                $errors[] = $e->getMessage();
            }
        }

        if (!$sent) {
            throw new PlatformException(
                'WhatsApp did not accept the message for any recipient. ' . ($errors[0] ?? ''),
                'rejected',
                false
            );
        }

        return PublishResult::make($sent[0], null, [
            'sent'   => count($sent),
            'failed' => count($errors),
            'ids'    => $sent,
        ]);
    }

    protected function templatePayload(string $to, string $template, PublishContext $context, ?string $mediaId): array {
        $language = $context->value('template_language') ?: 'en_US';
        $body     = trim($context->caption());

        $components = [];

        if ($mediaId) {
            $video = $context->video();
            $components[] = [
                'type'       => 'header',
                'parameters' => [[
                    'type'                       => $video ? 'video' : 'image',
                    $video ? 'video' : 'image'   => ['id' => $mediaId],
                ]],
            ];
        }

        if ($body !== '') {
            $components[] = [
                'type'       => 'body',
                // Template body variables are positional; the caption fills {{1}}.
                'parameters' => [['type' => 'text', 'text' => $this->clamp($body, 1024)]],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $template,
                'language' => ['code' => $language],
            ],
        ];

        if ($components) {
            $payload['template']['components'] = $components;
        }

        return $payload;
    }

    protected function freeformPayload(string $to, PublishContext $context, ?string $mediaId): array {
        $text = trim($context->caption());
        if ($cta = $context->value('cta')) {
            $text .= "\n\n" . $cta;
        }
        if ($link = $context->primaryLink()) {
            $text .= "\n" . $link;
        }
        $text = $this->clamp($text, 1024);

        if ($mediaId) {
            $video = $context->video();
            return [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => $video ? 'video' : 'image',
                ($video ? 'video' : 'image') => ['id' => $mediaId, 'caption' => $text],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $text ?: ' ', 'preview_url' => true],
        ];
    }

    /** Uploads once and reuses the media id across every recipient. */
    protected function uploadMedia(string $token, string $numberId, SocialMedia $media): ?string {
        $path = $media->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The media file could not be read from storage.');
        }

        $response = $this->http->withToken($token)
            ->attach('file', fopen($path, 'r'), $media->filename, ['Content-Type' => $media->mime])
            ->post($this->apiBase() . "/$numberId/media", [
                'messaging_product' => 'whatsapp',
                'type'              => $media->mime,
            ]);

        $body = $this->result($response, 'WhatsApp rejected the media upload.');

        return $body['id'] ?? null;
    }

    /**
     * The Cloud API reports delivery through webhooks, not a metrics endpoint,
     * so nothing is claimed here.
     */
    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        return null;
    }
}
