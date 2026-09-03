@php
    use App\Constants\SocialStatus;
    /** Per-platform status chips for a post row. $targets is a collection. */
@endphp

<div class="d-flex flex-wrap gap-1">
    @forelse($targets as $target)
        @php
            $class = match ($target->status) {
                SocialStatus::PUBLISHED => 'social-chip--ok',
                SocialStatus::FAILED    => 'social-chip--danger',
                SocialStatus::RETRYING, SocialStatus::SCHEDULED, SocialStatus::QUEUED => 'social-chip--warn',
                default => 'social-chip--muted',
            };
            $mark = match ($target->status) {
                SocialStatus::PUBLISHED  => 'las la-check',
                SocialStatus::FAILED     => 'las la-times',
                SocialStatus::PUBLISHING => 'las la-sync',
                SocialStatus::RETRYING   => 'las la-redo',
                SocialStatus::QUEUED, SocialStatus::SCHEDULED => 'las la-clock',
                default => 'las la-circle',
            };
        @endphp
        <span class="social-chip {{ $class }}"
              title="{{ SocialStatus::platformName($target->platform) }}: {{ $target->status_name }}{{ $target->error_message ? ' — ' . $target->error_message : '' }}">
            <i class="{{ SocialStatus::platformIcon($target->platform) }}" style="color: {{ SocialStatus::platformColor($target->platform) }}"></i>
            <i class="{{ $mark }}"></i>
        </span>
    @empty
        <span class="social-chip social-chip--muted">@lang('No platforms')</span>
    @endforelse
</div>
