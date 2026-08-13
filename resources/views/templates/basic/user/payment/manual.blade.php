@extends('Template::layouts.master')

@section('content')
    <div class="my-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card custom--card">
                        <div class="card-body  ">
                            <form action="{{ route('user.deposit.manual.update') }}" method="POST" class="disableSubmission" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="alert alert--base">
                                            <p class="mb-0"><i class="las la-info-circle"></i> @lang('You are requesting') <b>{{ showAmount($data['amount']) }}</b> @lang('to deposit.') @lang('Please pay')
                                                <b>{{ showAmount($data['final_amount'], currencyFormat: false) . ' ' . $data['method_currency'] }} </b> @lang('for successful payment.')
                                            </p>
                                        </div>

                                        <div class="mb-3">@php echo  $data->gateway->description @endphp</div>
                                    </div>

                                    <x-viser-form identifier="id" identifierValue="{{ $gateway->form_id }}" class="form-group" label="form--label" formClass="form--control md-style" />

                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn--base w-100">@lang('Pay Now')</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
