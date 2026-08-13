{{--
    Renders a category's icon safely.

    The `icon` column stores a bare CSS class string (e.g. "fas fa-newspaper"),
    NOT markup — echoing it directly printed the class name as visible text.
    This partial wraps it, tolerates the HTML form too, and falls back to the
    category image and then to a lettered tile so a card is never iconless.

    Expects: $category  (any model with ->icon, ->image, ->name)
             $fallback  optional Bootstrap-Icons class, default bi-folder2
--}}
@php
    $icon     = trim((string) ($category->icon ?? ''));
    $fallback = $fallback ?? 'bi-folder2';

    // Already markup (an <i>/<svg> tag) — trust it as-is.
    $isMarkup = $icon !== '' && str_starts_with($icon, '<');

    // Bare class string: Font Awesome (fas/far/fab/fa-), Line Awesome (las/la-)
    // or Bootstrap Icons (bi-). Anything else is treated as not-an-icon.
    $isClass = $icon !== '' && !$isMarkup
        && preg_match('/\b(fa[srlbd]?|la[srb]?|bi)\b|\b(fa|la|bi)-/', $icon);

    $hasImage = !empty($category->image);

    // Deterministic tint for the lettered fallback.
    $letter  = mb_strtoupper(mb_substr(trim((string) ($category->name ?? '?')), 0, 1));
    $palette = ['#4f46e5', '#0891b2', '#15803d', '#c026d3', '#ea580c', '#b45309'];
    $tint    = $palette[crc32((string) ($category->name ?? '')) % count($palette)];
@endphp

@if ($isMarkup)
    @php echo $icon; @endphp
@elseif ($isClass)
    <i class="{{ $icon }}" aria-hidden="true"></i>
@elseif ($hasImage)
    <img src="{{ getImage(getFilePath('category') . '/' . $category->image, getFileSize('category')) }}"
         alt="" class="w-cat-img" loading="lazy">
@elseif (!empty($category->name))
    {{-- No icon and no image: a lettered tile still reads as intentional. --}}
    <span class="w-cat-letter" style="background: {{ $tint }};" aria-hidden="true">{{ $letter }}</span>
@else
    <i class="bi {{ $fallback }}" aria-hidden="true"></i>
@endif
