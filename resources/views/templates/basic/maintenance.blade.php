@extends('Template::layouts.frontend')
@section('content')
    <section class="maintenance-page flex-column justify-content-center">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-7 text-center">
                    <div class="row justify-content-center">
                        <div class="col-sm-6 col-8 col-lg-12">
                            <img class="img-fluid mx-auto mb-5" src="{{ getImage(getFilePath('maintenance') . '/' . $maintenance?->data_values?->image, getFileSize('maintenance')) }}" alt="image">
                        </div>
                    </div>
                    <p class="mx-auto text-center">@php echo $maintenance->data_values->description @endphp</p>
                </div>
            </div>
        </div>
    </section>
@endsection
@push('style')
    <style>
        .maintenance-page {
            display: grid;
            place-content: center;
            width: 100vw;
            height: 100vh;
        }

        .maintenance-icon {
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
            aspect-ratio: 1;
            border-radius: 50%;
            background: #fff;
            font-size: 26px;
        }
    </style>
@endpush
