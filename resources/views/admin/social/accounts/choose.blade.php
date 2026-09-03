@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Constants\SocialStatus as S; @endphp

@section('panel')

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <div class="social-platform__icon" style="background: {{ S::platformColor($platform) }}; width:32px;height:32px;font-size:16px;">
                    <i class="{{ S::platformIcon($platform) }}"></i>
                </div>
                <h6 class="mb-0">@lang('Choose where to publish on') {{ S::platformName($platform) }}</h6>
            </div>

            <div class="card-body">
                <p class="text-muted">
                    @lang('Authorisation succeeded. This account administers more than one destination - pick the one Quiz Mitra should publish to. You can connect the others afterwards.')
                </p>

                <form action="{{ route('admin.social.accounts.choose', $platform) }}" method="POST">
                    @csrf
                    <div class="d-grid gap-2 mb-4">
                        @foreach($choices as $index => $choice)
                            <label class="social-select {{ ($choice['can_publish'] ?? true) ? '' : 'is-disabled' }}">
                                <input type="radio" name="choice" value="{{ $choice['id'] }}"
                                       {{ $index === 0 && ($choice['can_publish'] ?? true) ? 'checked' : '' }}
                                       {{ ($choice['can_publish'] ?? true) ? '' : 'disabled' }}>
                                @if(!empty($choice['avatar']))
                                    <img src="{{ $choice['avatar'] }}" class="social-avatar" alt="">
                                @else
                                    <div class="social-platform__icon" style="background: {{ S::platformColor($platform) }}">
                                        <i class="{{ S::platformIcon($platform) }}"></i>
                                    </div>
                                @endif
                                <div class="flex-grow-1 min-w-0">
                                    <div class="social-platform__name">{{ $choice['name'] ?? $choice['id'] }}</div>
                                    <div class="social-platform__meta">
                                        {{ $choice['username'] ?? '' }}
                                        @isset($choice['followers'])
                                            · {{ number_format($choice['followers']) }} @lang('followers')
                                        @endisset
                                        @if(($choice['kind'] ?? null) === 'organization')
                                            · @lang('Company page')
                                        @elseif(($choice['kind'] ?? null) === 'person')
                                            · @lang('Personal profile')
                                        @endif
                                    </div>
                                    @unless($choice['can_publish'] ?? true)
                                        <span class="social-select__reason">
                                            @lang('This account does not have permission to create content here.')
                                        </span>
                                    @endunless
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn--primary"><i class="las la-check"></i> @lang('Use this destination')</button>
                        <a href="{{ route('admin.social.accounts.index') }}" class="btn btn--dark">@lang('Cancel')</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
    "use strict";
    (function ($) {
        $(document).on('change', 'input[name="choice"]', function () {
            $('.social-select').removeClass('is-checked');
            $(this).closest('.social-select').addClass('is-checked');
        }).trigger('change');
    })(jQuery);
</script>
@endpush
