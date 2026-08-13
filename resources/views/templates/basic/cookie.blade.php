@extends('Template::layouts.frontend')
@section('content')
    <section class="mt-120 mb-120">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="cookie-data">
                        @php
                            echo $cookie->data_values->description;
                        @endphp
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .cookie-data {
            padding: 40px;
            border: 1px solid hsl(var(--border-color)/0.7);
            border-radius: 12px;
        }

        @media screen and (max-width: 767px) {
            .cookie-data {
                padding: 24px;
            }
        }

        .cookie-data h5 {
            margin-bottom: 16px
        }

        .cookie-data p {
            font-size: 16px;
        }

        .cookie-data ul {
            padding-left: 20px;
            margin-top: 12px;
            margin-left: 12px;
        }

        .cookie-data ul li {
            list-style: disc;
            font-size: 16px;
        }

        .cookie-data ul li:not(:last-child) {
            margin-bottom: 12px;
        }
    </style>
@endpush
