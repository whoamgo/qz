@extends('Template::layouts.master')
@section('content')
    <div class="container">
        <div class="my-120">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card custom--card">
                        <div class="card-body">
                            <form class="register" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="auth-user-pic">
                                    <img class="auth-user-pic__thumb" src="{{ getImage(getFilePath('userProfile') . '/' . auth()->user()->image ?? '', getFileSize('userProfile')) }}" alt="img">
                                    <div class="auth-user-pic__btns">
                                        <input class="d-none" type="file" name="image" id="auth-user-pic" accept=".png,.jpg,.jpeg">
                                        <label class="btn btn--sm btn-outline--base" for="auth-user-pic">@lang('Change picture')</label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-sm-6">
                                        <label class="form--label">@lang('First Name')</label>
                                        <input type="text" class="form--control md-style" name="firstname" value="{{ $user->firstname }}" required>
                                    </div>
                                    <div class="form-group col-sm-6">
                                        <label class="form--label">@lang('Last Name')</label>
                                        <input type="text" class="form--control md-style" name="lastname" value="{{ $user->lastname }}" required>
                                    </div>

                                    <div class="form-group col-sm-6">
                                        <label class="form--label">@lang('E-mail Address')</label>
                                        <input class="form--control md-style" value="{{ $user->email }}" readonly>
                                    </div>
                                    <div class="form-group col-sm-6">
                                        <label class="form--label">@lang('Mobile Number')</label>
                                        <input class="form--control md-style" value="{{ $user->mobile }}" readonly>
                                    </div>

                                    <div class="form-group col-sm-6">
                                        <label class="form--label">@lang('Address')</label>
                                        <input type="text" class="form--control md-style" name="address" value="{{ $user->address }}">
                                    </div>
                                    <div class="form-group col-sm-6">
                                        <label class="form--label">@lang('State')</label>
                                        <input type="text" class="form--control md-style" name="state" value="{{ $user->state }}">
                                    </div>
                                    <div class="form-group col-sm-4">
                                        <label class="form--label">@lang('Zip Code')</label>
                                        <input type="text" class="form--control md-style" name="zip" value="{{ $user->zip }}">
                                    </div>

                                    <div class="form-group col-sm-4">
                                        <label class="form--label">@lang('City')</label>
                                        <input type="text" class="form--control md-style" name="city" value="{{ $user->city }}">
                                    </div>

                                    <div class="form-group col-sm-4">
                                        <label class="form--label">@lang('Country')</label>
                                        <input class="form--control md-style" value="{{ $user->country_name }}" disabled>
                                    </div>

                                </div>

                                <button type="submit" class="w-100 btn btn--base">@lang('Submit')</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('script')
    <script>
        (function($) {
            "use strict";
            let inputField = $("#auth-user-pic");
            let uploadImg = $(".auth-user-pic__thumb");


            inputField.on("change", function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        const result = reader.result;
                        uploadImg.attr("src", result);
                    };
                    reader.readAsDataURL(file);
                }
            });

        })(jQuery)
    </script>
@endpush
