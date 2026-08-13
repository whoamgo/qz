@props(['icon' => 'bi-inbox', 'title' => 'Nothing here yet', 'message' => '', 'actionUrl' => null, 'actionLabel' => null])
<div class="w-empty">
    <div class="w-empty-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
    <h3>{{ $title }}</h3>
    @if ($message)<p>{{ $message }}</p>@endif
    @if ($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="btn w-btn-primary">{{ $actionLabel }}</a>
    @endif
</div>
