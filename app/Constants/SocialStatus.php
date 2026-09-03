<?php

namespace App\Constants;

/**
 * Every state and enum the Social Media Center uses.
 *
 * States are stored as strings rather than integers: the publishing pipeline is
 * inspected constantly from logs and the queue UI, and a readable value there is
 * worth more than the few bytes an integer would save.
 */
class SocialStatus {

    /* ---------------------------------------------------------------- Platforms */

    const YOUTUBE   = 'youtube';
    const INSTAGRAM = 'instagram';
    const FACEBOOK  = 'facebook';
    const X         = 'x';
    const LINKEDIN  = 'linkedin';
    const TELEGRAM  = 'telegram';
    const WHATSAPP  = 'whatsapp';
    const THREADS   = 'threads';

    const PLATFORMS = [
        self::YOUTUBE   => 'YouTube',
        self::INSTAGRAM => 'Instagram',
        self::FACEBOOK  => 'Facebook',
        self::X         => 'X (Twitter)',
        self::LINKEDIN  => 'LinkedIn',
        self::TELEGRAM  => 'Telegram',
        self::WHATSAPP  => 'WhatsApp Business',
        self::THREADS   => 'Threads',
    ];

    /** Line Awesome / Font Awesome icon per platform, used across the UI. */
    const PLATFORM_ICONS = [
        self::YOUTUBE   => 'lab la-youtube',
        self::INSTAGRAM => 'lab la-instagram',
        self::FACEBOOK  => 'lab la-facebook',
        self::X         => 'lab la-twitter',
        self::LINKEDIN  => 'lab la-linkedin',
        self::TELEGRAM  => 'lab la-telegram',
        self::WHATSAPP  => 'lab la-whatsapp',
        self::THREADS   => 'las la-at',
    ];

    const PLATFORM_COLORS = [
        self::YOUTUBE   => '#ff0000',
        self::INSTAGRAM => '#e1306c',
        self::FACEBOOK  => '#1877f2',
        self::X         => '#0f1419',
        self::LINKEDIN  => '#0a66c2',
        self::TELEGRAM  => '#229ed9',
        self::WHATSAPP  => '#25d366',
        self::THREADS   => '#000000',
    ];

    /* --------------------------------------------------------- Account statuses */

    const ACCOUNT_DISCONNECTED     = 0;
    const ACCOUNT_CONNECTED        = 1;
    const ACCOUNT_TOKEN_EXPIRED    = 2;
    const ACCOUNT_PERMISSION_ISSUE = 3;
    const ACCOUNT_ERROR            = 4;

    const ACCOUNT_STATUSES = [
        self::ACCOUNT_DISCONNECTED     => 'Not Connected',
        self::ACCOUNT_CONNECTED        => 'Connected',
        self::ACCOUNT_TOKEN_EXPIRED    => 'Token Expired',
        self::ACCOUNT_PERMISSION_ISSUE => 'Permission Issue',
        self::ACCOUNT_ERROR            => 'Error',
    ];

    /* ------------------------------------------------------------- Post states */

    const DRAFT             = 'draft';
    const PENDING_APPROVAL  = 'pending_approval';
    const APPROVED          = 'approved';
    const SCHEDULED         = 'scheduled';
    const QUEUED            = 'queued';
    const PUBLISHING        = 'publishing';
    const PUBLISHED         = 'published';
    const PARTIALLY_PUBLISHED = 'partially_published';
    const FAILED            = 'failed';
    const RETRYING          = 'retrying';
    const CANCELLED         = 'cancelled';
    const REJECTED          = 'rejected';

    const POST_STATUSES = [
        self::DRAFT               => 'Draft',
        self::PENDING_APPROVAL    => 'Pending Approval',
        self::APPROVED            => 'Approved',
        self::SCHEDULED           => 'Scheduled',
        self::QUEUED              => 'Queued',
        self::PUBLISHING          => 'Publishing',
        self::PUBLISHED           => 'Published',
        self::PARTIALLY_PUBLISHED => 'Partially Published',
        self::FAILED              => 'Failed',
        self::RETRYING            => 'Retrying',
        self::CANCELLED           => 'Cancelled',
        self::REJECTED            => 'Rejected',
    ];

    /** Bootstrap contextual class per state, for badges across the panel. */
    const STATUS_CLASSES = [
        self::DRAFT               => 'secondary',
        self::PENDING_APPROVAL    => 'warning',
        self::APPROVED            => 'info',
        self::SCHEDULED           => 'warning',
        self::QUEUED              => 'info',
        self::PUBLISHING          => 'primary',
        self::PUBLISHED           => 'success',
        self::PARTIALLY_PUBLISHED => 'warning',
        self::FAILED              => 'danger',
        self::RETRYING            => 'warning',
        self::CANCELLED           => 'dark',
        self::REJECTED            => 'danger',
    ];

    /** States that must not be edited or re-published from the UI. */
    const TERMINAL_STATES = [self::PUBLISHED, self::CANCELLED];

    /* --------------------------------------------------------- Approval states */

    const APPROVAL_NOT_REQUIRED = 'not_required';
    const APPROVAL_PENDING      = 'pending';
    const APPROVAL_APPROVED     = 'approved';
    const APPROVAL_REJECTED     = 'rejected';

    /* -------------------------------------------------------------- Job states */

    const JOB_QUEUED     = 'queued';
    const JOB_PROCESSING = 'processing';
    const JOB_COMPLETED  = 'completed';
    const JOB_FAILED     = 'failed';
    const JOB_CANCELLED  = 'cancelled';

    const JOB_PUBLISH        = 'publish';
    const JOB_SYNC_ANALYTICS = 'sync_analytics';
    const JOB_REFRESH_TOKEN  = 'refresh_token';
    const JOB_SYNC_INBOX     = 'sync_inbox';

    /* ------------------------------------------------------------------- Roles */

    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN       = 'admin';
    const ROLE_MANAGER     = 'social_media_manager';
    const ROLE_EDITOR      = 'editor';
    const ROLE_VIEWER      = 'viewer';

    const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN       => 'Admin',
        self::ROLE_MANAGER     => 'Social Media Manager',
        self::ROLE_EDITOR      => 'Editor',
        self::ROLE_VIEWER      => 'Viewer',
    ];

    /* ------------------------------------------------------------ Content types */

    const CONTENT_TEXT  = 'text';
    const CONTENT_IMAGE = 'image';
    const CONTENT_VIDEO = 'video';
    const CONTENT_REEL  = 'reel';
    const CONTENT_SHORT = 'short';
    const CONTENT_LINK  = 'link';

    const CONTENT_TYPES = [
        self::CONTENT_TEXT  => 'Text only',
        self::CONTENT_IMAGE => 'Image',
        self::CONTENT_VIDEO => 'Video',
        self::CONTENT_REEL  => 'Reel / Vertical video',
        self::CONTENT_SHORT => 'Short',
        self::CONTENT_LINK  => 'Link',
    ];

    public static function platformName(?string $platform): string {
        return self::PLATFORMS[$platform] ?? ucfirst((string) $platform);
    }

    public static function platformIcon(?string $platform): string {
        return self::PLATFORM_ICONS[$platform] ?? 'las la-share-alt';
    }

    public static function platformColor(?string $platform): string {
        return self::PLATFORM_COLORS[$platform] ?? '#6c757d';
    }

    public static function statusName(?string $status): string {
        return self::POST_STATUSES[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function statusClass(?string $status): string {
        return self::STATUS_CLASSES[$status] ?? 'secondary';
    }
}
