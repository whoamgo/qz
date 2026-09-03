@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Services\Social\SocialPermission; $canMove = SocialPermission::allows(SocialPermission::SCHEDULE); @endphp

@section('panel')

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline--dark" id="calPrev"><i class="las la-angle-left"></i></button>
            <h6 class="mb-0" id="calTitle" style="min-width:180px;text-align:center"></h6>
            <button class="btn btn-sm btn-outline--dark" id="calNext"><i class="las la-angle-right"></i></button>
            <button class="btn btn-sm btn-outline--primary" id="calToday">@lang('Today')</button>
        </div>

        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline--primary active" data-view="month">@lang('Month')</button>
            <button type="button" class="btn btn-outline--primary" data-view="week">@lang('Week')</button>
            <button type="button" class="btn btn-outline--primary" data-view="day">@lang('Day')</button>
            <button type="button" class="btn btn-outline--primary" data-view="list">@lang('List')</button>
        </div>
    </div>

    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 mb-3">
            @foreach($legend as $status => $meta)
                <span class="social-chip">
                    <span class="social-dot" style="background: {{ $meta['colour'] }}"></span>
                    {{ __($meta['label']) }}
                </span>
            @endforeach
            @if($canMove)
                <span class="social-chip social-chip--muted">
                    <i class="las la-arrows-alt"></i> @lang('Drag a scheduled post to move it')
                </span>
            @endif
        </div>

        <div id="calLoading" class="d-none">
            <div class="social-skeleton mb-2" style="height:40px"></div>
            <div class="social-skeleton" style="height:320px"></div>
        </div>

        <div id="calGrid" class="social-scroll-x"></div>
    </div>
</div>

@endsection

@push('script')
<script>
"use strict";
(function ($) {

    var CAN_MOVE = @json($canMove);
    var ROUTES   = {
        events: '{{ route('admin.social.calendar.events') }}',
        move:   '{{ route('admin.social.calendar.move') }}'
    };
    var CSRF = '{{ csrf_token() }}';

    var view    = 'month';
    var cursor  = new Date();
    var events  = [];

    var DOW = ['{{ __('Sun') }}', '{{ __('Mon') }}', '{{ __('Tue') }}', '{{ __('Wed') }}',
               '{{ __('Thu') }}', '{{ __('Fri') }}', '{{ __('Sat') }}'];
    var MONTHS = ['{{ __('January') }}','{{ __('February') }}','{{ __('March') }}','{{ __('April') }}',
                  '{{ __('May') }}','{{ __('June') }}','{{ __('July') }}','{{ __('August') }}',
                  '{{ __('September') }}','{{ __('October') }}','{{ __('November') }}','{{ __('December') }}'];

    /* ------------------------------------------------------ Date helpers */

    function iso(d) {
        // Local date, not UTC: toISOString() would shift the day either side of
        // midnight for anyone not on UTC.
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function rangeFor() {
        var start, end;

        if (view === 'day') {
            start = new Date(cursor); end = new Date(cursor);
        } else if (view === 'week') {
            start = new Date(cursor); start.setDate(start.getDate() - start.getDay());
            end = new Date(start); end.setDate(end.getDate() + 6);
        } else if (view === 'list') {
            start = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            end   = new Date(cursor.getFullYear(), cursor.getMonth() + 3, 0);
        } else {
            start = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            start.setDate(start.getDate() - start.getDay());
            end = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
            end.setDate(end.getDate() + (6 - end.getDay()));
        }

        return { start: start, end: end };
    }

    /* ------------------------------------------------------------- Loading */

    function load() {
        var r = rangeFor();

        $('#calLoading').removeClass('d-none');
        $('#calGrid').addClass('d-none');

        $.get(ROUTES.events, { start: iso(r.start), end: iso(r.end) }, function (data) {
            events = data;
            render();
        }).always(function () {
            $('#calLoading').addClass('d-none');
            $('#calGrid').removeClass('d-none');
        });
    }

    function eventsOn(dateStr) {
        return events.filter(function (e) { return e.date === dateStr; });
    }

    /* ------------------------------------------------------------ Rendering */

    function render() {
        $('#calTitle').text(
            view === 'day'
                ? cursor.getDate() + ' ' + MONTHS[cursor.getMonth()] + ' ' + cursor.getFullYear()
                : MONTHS[cursor.getMonth()] + ' ' + cursor.getFullYear()
        );

        if (view === 'list') return renderList();
        renderGrid();
    }

    function renderGrid() {
        var r     = rangeFor();
        var today = iso(new Date());
        var html  = '';

        var columns = view === 'day' ? 1 : 7;

        if (columns === 7) {
            for (var d = 0; d < 7; d++) html += '<div class="social-calendar__dow">' + DOW[d] + '</div>';
        }

        var cursorDate = new Date(r.start);

        while (cursorDate <= r.end) {
            var key   = iso(cursorDate);
            var other = view === 'month' && cursorDate.getMonth() !== cursor.getMonth();

            html += '<div class="social-calendar__cell' + (other ? ' is-other-month' : '')
                  + (key === today ? ' is-today' : '') + '" data-date="' + key + '">'
                  + '<div class="social-calendar__date">' + cursorDate.getDate() + '</div>';

            eventsOn(key).forEach(function (e) { html += eventHtml(e); });

            html += '</div>';
            cursorDate.setDate(cursorDate.getDate() + 1);
        }

        $('#calGrid').html(
            '<div class="social-calendar" style="grid-template-columns: repeat(' + columns + ', minmax(0,1fr))">' + html + '</div>'
        );

        if (CAN_MOVE) bindDragDrop();
    }

    function eventHtml(e) {
        var icons = (e.platforms || []).map(function (p) {
            return '<i class="' + p.icon + '" style="color:' + p.colour + '"></i>';
        }).join('');

        return '<a class="social-event" href="' + e.url + '" style="border-left-color:' + e.colour + '"'
             + (CAN_MOVE && e.movable ? ' draggable="true"' : '')
             + ' data-id="' + e.id + '" data-time="' + (e.time || '09:00') + '"'
             + ' title="' + escapeHtml(e.title) + ' — ' + escapeHtml(e.statusName) + '">'
             + '<span class="social-event__time">' + (e.time || '') + '</span> '
             + escapeHtml(truncate(e.title, 28))
             + '<span class="social-event__platforms">' + icons + '</span></a>';
    }

    function renderList() {
        var rows = events.slice().sort(function (a, b) {
            return (a.start || '').localeCompare(b.start || '');
        });

        if (!rows.length) {
            $('#calGrid').html('<div class="social-empty"><i class="las la-calendar"></i>'
                + '<h6>{{ __('Nothing in this range') }}</h6>'
                + '<p>{{ __('Schedule a post to see it here.') }}</p></div>');
            return;
        }

        var html = '<div class="table-responsive"><table class="table table--light style--two mb-0"><thead><tr>'
                 + '<th>{{ __('When') }}</th><th>{{ __('Post') }}</th><th>{{ __('Platforms') }}</th>'
                 + '<th>{{ __('Status') }}</th></tr></thead><tbody>';

        rows.forEach(function (e) {
            var icons = (e.platforms || []).map(function (p) {
                return '<i class="' + p.icon + '" style="color:' + p.colour + '"></i>';
            }).join(' ');

            html += '<tr><td>' + (e.date || '') + ' ' + (e.time || '') + '</td>'
                  + '<td><a href="' + e.url + '">' + escapeHtml(e.title) + '</a></td>'
                  + '<td>' + icons + '</td>'
                  + '<td><span class="badge" style="background:' + e.colour + ';color:#fff">'
                  + escapeHtml(e.statusName) + '</span></td></tr>';
        });

        $('#calGrid').html(html + '</tbody></table></div>');
    }

    /* ---------------------------------------------------------- Drag & drop */

    function bindDragDrop() {
        var draggedId = null, draggedTime = '09:00';

        $('#calGrid').off('dragstart dragend dragover dragleave drop');

        $('#calGrid').on('dragstart', '.social-event', function (e) {
            draggedId   = $(this).data('id');
            draggedTime = $(this).data('time') || '09:00';
            $(this).addClass('is-dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            // Firefox refuses to start a drag without data set.
            e.originalEvent.dataTransfer.setData('text/plain', String(draggedId));
        });

        $('#calGrid').on('dragend', '.social-event', function () {
            $(this).removeClass('is-dragging');
            $('.social-calendar__cell').removeClass('is-drop-target');
        });

        $('#calGrid').on('dragover', '.social-calendar__cell', function (e) {
            e.preventDefault();
            $(this).addClass('is-drop-target');
        });

        $('#calGrid').on('dragleave', '.social-calendar__cell', function () {
            $(this).removeClass('is-drop-target');
        });

        $('#calGrid').on('drop', '.social-calendar__cell', function (e) {
            e.preventDefault();
            $(this).removeClass('is-drop-target');

            if (!draggedId) return;

            var date = $(this).data('date');

            $.post(ROUTES.move, {
                _token: CSRF,
                post_id: draggedId,
                // The original time of day is kept - a drag changes the day,
                // not the carefully chosen posting hour.
                scheduled_at: date + ' ' + draggedTime
            }).done(function (res) {
                iziToast.success({ message: res.message, position: 'topRight' });
                load();
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON || {}).message || '{{ __('That post could not be moved.') }}';
                iziToast.error({ message: msg, position: 'topRight' });
                load();
            });

            draggedId = null;
        });
    }

    /* -------------------------------------------------------------- Controls */

    $('#calPrev').on('click', function () { step(-1); });
    $('#calNext').on('click', function () { step(1); });
    $('#calToday').on('click', function () { cursor = new Date(); load(); });

    function step(direction) {
        if (view === 'day')       cursor.setDate(cursor.getDate() + direction);
        else if (view === 'week') cursor.setDate(cursor.getDate() + 7 * direction);
        else                      cursor.setMonth(cursor.getMonth() + direction);
        load();
    }

    $('[data-view]').on('click', function () {
        $('[data-view]').removeClass('active');
        $(this).addClass('active');
        view = $(this).data('view');
        load();
    });

    /* --------------------------------------------------------------- Utils */

    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function truncate(s, n) { s = s || ''; return s.length > n ? s.slice(0, n - 1) + '…' : s; }

    load();

})(jQuery);
</script>
@endpush
