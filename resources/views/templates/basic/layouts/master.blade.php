@extends('Template::layouts.app')

@section('app')
    @yield('content')
@endsection


@push('script')
    <script>
        (function($) {
            "use strict";
            Array.from(document.querySelectorAll('table')).forEach(table => {
                let heading = table.querySelectorAll('thead tr th');
                Array.from(table.querySelectorAll('tbody tr')).forEach((row) => {
                    const columns = row.querySelectorAll('td');
                    if (columns.length !== heading.length) return;
                    columns.forEach((col, i) => {
                        col.setAttribute('data-label', heading[i].innerText);
                    });
                });
            });
        })(jQuery)
    </script>
@endpush
