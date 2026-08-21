{{-- Sortable column header. Props: $col (sort key), $label. Reads the active
     ?sort= from the request and shows a caret on the active column. --}}
@php $active = ($sort ?? '') === $col; @endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $col, 'page' => null]) }}"
   class="text-decoration-none {{ $active ? 'text--primary fw-bold' : 'text-muted' }}">
    {{ __($label) }}
    <i class="las {{ $active ? 'la-sort-down' : 'la-sort' }}"></i>
</a>
