@php
    /**
     * One KPI tile.
     *
     * $value is rendered as-is when it is a string (so "—" can mean "this
     * platform reports no such metric") and formatted with thousands separators
     * when it is a number.
     */
    $display = is_numeric($value ?? null) ? number_format((float) $value) : ($value ?? '—');
@endphp

<div class="social-kpi">
    <div class="social-kpi__icon" style="background: {{ $colour ?? '#5b6ef5' }}">
        <i class="{{ $icon ?? 'las la-chart-bar' }}"></i>
    </div>
    <div>
        <div class="social-kpi__value">{{ $display }}</div>
        <div class="social-kpi__label">{{ __($label) }}</div>
        @isset($hint)
            <div class="social-kpi__hint">{{ $hint }}</div>
        @endisset
    </div>
</div>
