{{-- A consistent empty state: what is missing, and the one thing to do next. --}}
<div class="social-empty">
    <i class="{{ $icon ?? 'las la-inbox' }}"></i>
    <h6>{{ __($title ?? 'Nothing here yet') }}</h6>
    <p class="mb-3">{{ __($message ?? '') }}</p>
    @isset($actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn--primary btn-sm">
            <i class="las la-plus"></i> {{ __($actionLabel ?? 'Create') }}
        </a>
    @endisset
</div>
