@extends('Template::layouts.frontend')
@section('content')
    <section class="mt-120 mb-120">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="policy-data">
                        @php
                            echo $policy->data_values->details;
                        @endphp
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection


@push('style')
    <style>
        .policy-data {
            padding: 40px;
            border: 1px solid hsl(var(--border-color)/0.7);
            border-radius: 12px;
        }

        @media screen and (max-width: 767px) {
            .policy-data {
                padding: 24px;
            }
        }

        .policy-data h5 {
            margin-bottom: 16px
        }

        .policy-data p {
            font-size: 16px;
        }

        .policy-data ul {
            padding-left: 20px;
            margin-top: 12px;
            margin-left: 12px;
        }

        .policy-data ul li {
            list-style: disc;
            font-size: 16px;
        }

        .policy-data ul li:not(:last-child) {
            margin-bottom: 12px;
        }
    </style>
@endpush
