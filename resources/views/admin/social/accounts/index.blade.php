@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Constants\SocialStatus as S; @endphp

@section('panel')

<div class="social-note mb-4">
    <i class="las la-shield-alt"></i>
    <strong>@lang('About credentials.')</strong>
    @lang('Access tokens are encrypted before they are stored and are never sent to the browser. This page shows connection status only - no page here can display a token, and none is written to the activity log.')
</div>

@unless($canManage)
    <div class="alert alert-info">
        <i class="las la-lock"></i>
        @lang('Connecting and disconnecting accounts is restricted to the Super Admin, because it grants long-lived credentials that can publish to a live audience.')
    </div>
@endunless

@foreach($platforms as $platform)
    <div class="card mb-4" id="platform-{{ $platform['key'] }}">
        <div class="card-header d-flex align-items-center gap-3 flex-wrap">
            <div class="social-platform__icon" style="background: {{ $platform['color'] }}; width:36px;height:36px;font-size:18px;">
                <i class="{{ $platform['icon'] }}"></i>
            </div>
            <h6 class="mb-0 flex-grow-1">{{ $platform['name'] }}</h6>

            @if($platform['configured'])
                <span class="badge badge--success">@lang('Configured')</span>
            @else
                <span class="badge badge--secondary">@lang('Not configured')</span>
            @endif

            @if($canManage && $platform['configured'] && $platform['auth'] !== 'token')
                <a href="{{ route('admin.social.accounts.connect', $platform['key']) }}" class="btn btn-sm btn--primary">
                    <i class="las la-plug"></i> {{ $platform['accounts']->count() ? __('Connect another') : __('Connect') }}
                </a>
            @endif
        </div>

        <div class="card-body">
            {{-- Credentials missing: say exactly which variables to set. --}}
            @unless($platform['configured'])
                <div class="alert alert-secondary small mb-3">
                    @lang('Add these to the server environment (.env), then reload the panel:')
                    <ul class="mb-0 mt-2">
                        @foreach($platform['env_keys'] as $key)
                            <li><code>{{ $key }}</code></li>
                        @endforeach
                    </ul>
                    <div class="mt-2">
                        @lang('Redirect URI to register with the platform:')
                        <code>{{ rtrim(config('social.redirect_base'), '/') }}/admin/social/accounts/callback/{{ $platform['key'] }}</code>
                    </div>
                </div>
            @endunless

            {{-- ----------------------------------------------- Account cards --}}
            @if($platform['accounts']->count())
                <div class="social-platform-grid mb-3">
                    @foreach($platform['accounts'] as $account)
                        <div class="social-platform">
                            <div class="social-platform__head">
                                @if($account->avatar_url)
                                    <img src="{{ $account->avatar_url }}" class="social-avatar" alt="">
                                @else
                                    <div class="social-platform__icon" style="background: {{ $platform['color'] }}">
                                        <i class="{{ $platform['icon'] }}"></i>
                                    </div>
                                @endif
                                <div class="flex-grow-1 min-w-0">
                                    <div class="social-platform__name text-truncate">{{ $account->display_name }}</div>
                                    <div class="social-platform__meta text-truncate">{{ $account->username ?: '—' }}</div>
                                </div>
                                @if($account->is_default)
                                    <span class="badge badge--primary">@lang('Default')</span>
                                @endif
                            </div>

                            <div class="social-platform__body">
                                <div class="mb-2">
                                    <span class="badge badge--{{ $account->status_class }}">{{ $account->status_name }}</span>
                                    @if($account->token_expires_at)
                                        <span class="social-chip {{ $account->tokenExpired() ? 'social-chip--danger' : 'social-chip--muted' }}">
                                            <i class="las la-key"></i>
                                            {{ $account->tokenExpired() ? __('Token expired') : __('Expires') . ' ' . diffForHumans($account->token_expires_at) }}
                                        </span>
                                    @endif
                                </div>

                                @if($account->last_error)
                                    <div class="alert alert-danger py-2 px-3 small mb-2">{{ $account->last_error }}</div>
                                @endif

                                <div class="social-platform__stats">
                                    <div class="social-platform__stat">
                                        <b>{{ number_format($account->followers) }}</b><span>@lang('Followers')</span>
                                    </div>
                                    <div class="social-platform__stat">
                                        <b>{{ $account->posts()->where('status', S::PUBLISHED)->count() }}</b><span>@lang('Published')</span>
                                    </div>
                                    <div class="social-platform__stat">
                                        <b>{{ $account->last_sync_at ? $account->last_sync_at->diffForHumans(null, true) : '—' }}</b><span>@lang('Last sync')</span>
                                    </div>
                                </div>
                            </div>

                            @if($canManage)
                                <div class="social-platform__foot">
                                    <form action="{{ route('admin.social.accounts.test', $account->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline--primary"><i class="las la-vial"></i> @lang('Test')</button>
                                    </form>
                                    <form action="{{ route('admin.social.accounts.sync', $account->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline--dark"><i class="las la-sync"></i> @lang('Sync')</button>
                                    </form>

                                    @if($platform['auth'] !== 'token' && $platform['configured'])
                                        <a href="{{ route('admin.social.accounts.connect', $platform['key']) }}"
                                           class="btn btn-sm btn-outline--warning">
                                            <i class="las la-redo"></i> @lang('Reconnect')
                                        </a>
                                    @endif

                                    @unless($account->is_default)
                                        <form action="{{ route('admin.social.accounts.default', $account->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline--dark">@lang('Make default')</button>
                                        </form>
                                    @endunless

                                    @if($account->isConnected())
                                        <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-action="{{ route('admin.social.accounts.disconnect', $account->id) }}"
                                                data-question="@lang('Disconnect this account? The stored credentials are erased, and scheduled posts for it will fail until it is reconnected.')">
                                            <i class="las la-unlink"></i> @lang('Disconnect')
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-action="{{ route('admin.social.accounts.delete', $account->id) }}"
                                                data-question="@lang('Remove this account card?')">
                                            <i class="las la-trash"></i> @lang('Remove')
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ------------------------------- Token-based connection forms --}}
            @if($canManage && $platform['auth'] === 'token')
                @if($platform['key'] === S::TELEGRAM)
                    <form action="{{ route('admin.social.accounts.token', $platform['key']) }}" method="POST" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">@lang('Bot token')</label>
                            <input type="password" name="bot_token" class="form-control" required autocomplete="new-password"
                                   placeholder="123456:ABC-DEF...">
                            <small class="social-counter">@lang('From @BotFather. Stored encrypted; never shown again.')</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('Channel / chat ID')</label>
                            <input type="text" name="chat_id" class="form-control" required placeholder="@quizmitra or -1001234567890">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn--primary w-100"><i class="las la-plug"></i> @lang('Connect Telegram')</button>
                        </div>
                        <div class="col-12">
                            <div class="social-note">
                                @lang('The bot must be an administrator of the channel with permission to post. The credentials are verified against Telegram before anything is saved.')
                            </div>
                        </div>
                    </form>
                @endif

                @if($platform['key'] === S::WHATSAPP)
                    <form action="{{ route('admin.social.accounts.token', $platform['key']) }}" method="POST" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">@lang('System user access token')</label>
                            <input type="password" name="access_token" class="form-control" required autocomplete="new-password">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">@lang('Phone number ID')</label>
                            <input type="text" name="phone_number_id" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">@lang('Business account ID')</label>
                            <input type="text" name="business_id" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">@lang('Recipient numbers')</label>
                            <textarea name="recipients" class="form-control" rows="2"
                                      placeholder="919999999999, 918888888888"></textarea>
                            <small class="social-counter">@lang('Country code first, no plus sign.')</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">@lang('Default template name')</label>
                            <input type="text" name="template_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn--primary w-100"><i class="las la-plug"></i> @lang('Connect WhatsApp')</button>
                        </div>
                        <div class="col-12">
                            <div class="social-note">
                                @lang('WhatsApp is messaging, not a feed. Business-initiated messages need a template approved by Meta, unless the recipient messaged you in the last 24 hours.')
                            </div>
                        </div>
                    </form>
                @endif
            @endif

            @if(!$platform['accounts']->count() && $platform['auth'] !== 'token')
                <p class="social-counter mb-0">
                    @if($platform['configured'])
                        @lang('No account connected yet.')
                    @else
                        @lang('Configure the credentials above before connecting an account.')
                    @endif
                </p>
            @endif
        </div>
    </div>
@endforeach

<x-confirmation-modal />

@endsection
