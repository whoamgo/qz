@props(['testimonial'])

@php
    $t = $testimonial;

    // Initials for the fallback avatar, e.g. "Megha Sharma" -> "MS".
    $parts    = preg_split('/\s+/', trim((string) $t->name)) ?: [];
    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr(count($parts) > 1 ? end($parts) : '', 0, 1));

    // Colour is derived from the name, so a given person always gets the same
    // swatch across page loads instead of flickering between renders.
    $palette = ['#ea580c', '#1e3a8a', '#15803d', '#c026d3', '#0891b2', '#b45309'];
    $accent  = $palette[crc32((string) $t->name) % count($palette)];

    $rating = max(1, min(5, (int) ($t->rating ?: 5)));
@endphp

<figure class="w-testimonial">
    <div class="w-testimonial-stars" role="img"
         aria-label="{{ $rating }} out of 5 stars">
        @for ($i = 1; $i <= 5; $i++)
            <i class="bi {{ $i <= $rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
        @endfor
    </div>

    <blockquote class="w-testimonial-quote">{{ $t->review }}</blockquote>

    <figcaption class="w-testimonial-foot">
        @if ($t->image)
            <img src="{{ $t->image }}" alt="" class="w-testimonial-avatar" width="42" height="42" loading="lazy">
        @else
            <span class="w-testimonial-avatar w-testimonial-initials"
                  style="background: {{ $accent }};" aria-hidden="true">{{ $initials ?: '?' }}</span>
        @endif

        <span class="w-testimonial-person">
            <strong>{{ $t->name }}</strong>
            @if ($t->designation)
                <span>{{ $t->designation }}</span>
            @endif
        </span>

        <i class="bi bi-quote w-testimonial-mark" style="color: {{ $accent }};" aria-hidden="true"></i>
    </figcaption>
</figure>
