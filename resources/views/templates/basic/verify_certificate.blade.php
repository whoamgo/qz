@extends('Template::layouts.frontend')
@section('content')
    <section class="maintenance-page flex-column justify-content-center my-120">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-7 text-center">
                    <div class="row justify-content-center">
                        <div class="col-lg-10 col-xxl-8 ">
                            <div class="card custom--card">
                                <div class="card-body">
                                    <div class="maintenance-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield-chevron">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" />
                                            <path d="M4 14l8 -3l8 3" />
                                        </svg>
                                    </div>
                                    <h2 class="maintenance-title">@lang('Certificate Verification')</h2>
                                    <h4>{{ __($user->fullname) }}</h4>
                                    <p class="maintenance-desc">@lang('has successfully completed')</p>
                                    <h4>{{ __($attendExam?->exam?->title) }}</h4>
                                    <div class="score-area">
                                        <h6>@lang('Score'):</h6>
                                        <p>{{ getAmount($attendExam->pass_percentage) }}%</p>
                                    </div>
                                    <div class="score-area">
                                        <h6>@lang('Date of Completion') :</h6>
                                        <p>{{ showDateTime($attendExam->exam->result_published_at, 'F d, Y') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .maintenance-desc {
            color: hsl(var(--black)/0.5);
            margin-block: 1px 8px;
            font-weight: 600;

            @media screen and (max-width: 424px) {
                font-size: 14px;
            }
        }

        .maintenance-icon {
            --s: 80px;
            width: var(--s);
            height: var(--s);
            border-radius: 50%;
            background-color: hsl(var(--success)/0.05);
            border: 1px solid hsl(var(--success)/0.2);
            color: hsl(var(--success));
            display: grid;
            place-content: center;
            font-size: 1.5rem;
            margin-inline: auto;
            margin-bottom: 30px;

            @media screen and (max-width: 575px) {
                margin-bottom: 10px;
                --s: 60px;
            }

            svg {
                width: 40px;
                height: auto;
                aspect-ratio: 1;

                @media screen and (max-width: 575px) {
                    width: 35px;
                }
            }
        }

        .maintenance-title {
            font-size: 38px;
            margin-bottom: 20px;

            @media screen and (max-width: 575px) {
                margin-bottom: 10px;
                font-size: 27px;
            }

            @media screen and (max-width: 424px) {
                font-size: 24px;
            }
        }



        .score-area {
            &:not(:last-child) {
                margin-top: 30px;
                margin-bottom: 8px;

                @media screen and (max-width: 575px) {
                    margin-top: 20px;
                }

                @media screen and (max-width: 424px) {
                    margin-bottom: 5px;
                }
            }

            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
            max-width: fit-content;
            margin-inline: auto;
            min-width: 340px;

            @media screen and (max-width: 575px) {
                font-size: 14px;
                gap: 5px;
            }

            @media screen and (max-width: 424px) {
                flex-direction: column;
                gap: 3px;
                justify-content: center;
                min-width: fit-content;
                text-align: center;
                font-size: 14px;
            }

            p {
                @media screen and (max-width: 424px) {
                    font-size: 14px;
                }
            }


            h6 {
                font-size: 18px;
                text-align: left;
                min-width: 180px;

                @media screen and (max-width: 575px) {
                    font-size: 16px;
                }

                @media screen and (max-width: 424px) {
                    text-align: center;
                }

            }
        }
    </style>
@endpush
