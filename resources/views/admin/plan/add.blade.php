@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-card-style">
                <div class="card-body">
                    <form action="{{ route('admin.plan.store', $plan?->id ?? 0) }}" method="POST">
                        @csrf

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Name')</p>
                                <p class="note">@lang('The name of the pricing plan (e.g., Basic, Pro, Premium).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="text" name="name" value="{{ $plan?->name ?? '' }}" required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Monthly Price')</p>
                                <p class="note">@lang('The subscription cost charged on a monthly basis.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input class="form-control" type="number" step="any" name="monthly_price" value="{{ getAmount($plan?->monthly_price) ?? '' }}" required>
                                    <span class="input-group-text">{{ gs('cur_text') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Yearly Price')</p>
                                <p class="note">@lang('The subscription cost charged annually (often discounted from monthly pricing).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input class="form-control" type="number" step="any" name="yearly_price" value="{{ getAmount($plan?->yearly_price) ?? '' }}" required>
                                    <span class="input-group-text">{{ gs('cur_text') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Monthly Exam Limit')</p>
                                <p class="note">@lang('The number of monthly exam limit included in the plan for the examination')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="number" name="monthly_exam" value="{{ $plan?->monthly_exam ?? '' }}" required>
                            </div>
                        </div>
                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Yearly Exam Limit')</p>
                                <p class="note">@lang('The number of yearly exam limit included in the plan for the examination.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="number" name="yearly_exam" value="{{ $plan?->yearly_exam ?? '' }}" required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Short Description')</p>
                                <p class="note">@lang('The short description about this plan.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="text" name="short_description" value="{{ $plan?->short_description ?? '' }}" required>
                            </div>
                        </div>

                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.plan.index') }}" />
@endpush


@push('style')
    <style>
        .iconpicker .iconpicker-item {
            color: rgba(0, 0, 0, 0.8);
        }

        .card.bg-card-style .card-heading {
            padding: 12px 16px;
            background-color: rgb(0, 0, 0, 5%);
            border-radius: 8px;
            margin-bottom: 24px
        }

        .card.bg-card-style .card-body {}

        .field-wrapper {
            display: flex;
            align-items: center;
            gap: 16px 24px;
            flex-wrap: wrap;
        }

        .field-wrapper:not(:last-child) {
            margin-bottom: 24px;
        }

        .field-wrapper-label {
            width: 320px;
        }

        .field-wrapper-label .title {
            color: black;
            font-weight: 600;
        }

        .field-wrapper-label .note {
            font-size: 0.75rem;
        }

        .field-wrapper-view {
            flex: 1;
            max-width: 700px;
        }

        .field-wrapper .image-upload-wrapper {
            height: 150px;
            position: relative;
            width: 160px;
        }

        @media (max-width: 767px) {
            .field-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }

            .field-wrapper-label {
                width: 100%;
            }
        }
    </style>
@endpush
