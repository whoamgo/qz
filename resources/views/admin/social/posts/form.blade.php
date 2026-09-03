@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\PlatformCapability;
    use App\Services\Social\SocialPermission;

    $can       = SocialPermission::current();
    $editing   = isset($post);
    $existing  = $editing ? $post->targets->keyBy('platform') : collect();
    $selected  = $editing ? $post->targets->pluck('platform')->all() : ($seed['platforms'] ?? []);

    // Values come from three places, in priority order: what the admin just
    // submitted (validation failure), the post being edited, then the Quiz Mitra
    // seed from a "Share to Social" click.
    $value = function (string $field, $default = null) use ($editing, $seed, $post) {
        return old($field, $editing ? ($post->{$field} ?? $default) : ($seed[$field] ?? $default));
    };

    $selectedMedia = $editing
        ? $post->media->where('pivot.role', 'primary')->pluck('id')->all()
        : [];
    $selectedThumb = $editing
        ? $post->media->where('pivot.role', 'thumbnail')->pluck('id')->all()
        : [];
@endphp

@section('panel')

<form action="{{ $editing ? route('admin.social.posts.update', $post->id) : route('admin.social.posts.store') }}"
      method="POST" id="socialWizard">
    @csrf

    {{--
        A key generated once when the form loads. If the admin double-submits -
        double click, browser retry - the server finds the first submission by
        this key and returns that post instead of creating a second one.
    --}}
    <input type="hidden" name="idempotency_key" id="idempotencyKey" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
    <input type="hidden" name="action" id="wizardAction" value="draft">

    {{-- ----------------------------------------------------------- Steps --}}
    <div class="social-steps" id="socialSteps">
        <div class="social-step is-active" data-step="1"><span class="social-step__num">1</span><span class="social-step__label">@lang('Content')</span></div>
        <div class="social-step" data-step="2"><span class="social-step__num">2</span><span class="social-step__label">@lang('Platforms')</span></div>
        <div class="social-step" data-step="3"><span class="social-step__num">3</span><span class="social-step__label">@lang('Customise')</span></div>
        <div class="social-step" data-step="4"><span class="social-step__num">4</span><span class="social-step__label">@lang('Preview')</span></div>
        <div class="social-step" data-step="5"><span class="social-step__num">5</span><span class="social-step__label">@lang('Publish')</span></div>
    </div>

    {{-- ============================================ STEP 1 - master content --}}
    <div class="social-pane is-active" data-pane="1">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0">@lang('Master content')</h6>
                        @if($aiEnabled && ($can[SocialPermission::USE_AI] ?? false))
                            <button type="button" class="btn btn-sm btn-outline--primary" id="aiGenerate">
                                <i class="las la-magic"></i> @lang('Generate Social Content')
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="form-label">@lang('Content title')</label>
                            <input type="text" name="title" class="form-control" maxlength="190"
                                   value="{{ $value('title') }}" placeholder="@lang('Can You Answer This GK Question?')">
                            <small class="social-counter" data-counter-for="title" data-max="190"></small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">@lang('Caption') <span class="text--danger">*</span></label>
                            <textarea name="caption" class="form-control" rows="5" id="masterCaption"
                                      placeholder="@lang('The main text of your post.')">{{ $value('caption') }}</textarea>
                            <small class="social-counter" data-counter-for="caption"></small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">@lang('Long description')</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="@lang('Used where a platform has a separate description field, such as YouTube.')">{{ $value('description') }}</textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">@lang('Hashtags')</label>
                                <input type="text" name="hashtags" class="form-control" id="masterHashtags"
                                       value="{{ $value('hashtags') }}" placeholder="#gk #quiz #currentaffairs">
                                @if($hashtagGroups->count())
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        @foreach($hashtagGroups as $group)
                                            <button type="button" class="btn btn-sm btn-outline--dark hashtag-group"
                                                    data-tags="{{ $group->asString() }}">
                                                {{ $group->name }} <span class="badge badge--secondary">{{ $group->hashtag_count }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Mentions / tags')</label>
                                <input type="text" name="mentions" class="form-control" maxlength="500"
                                       value="{{ $value('mentions') }}" placeholder="@quizmitra">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Call to action')</label>
                                <input type="text" name="cta" class="form-control" maxlength="190"
                                       value="{{ $value('cta') }}" placeholder="@lang('Play now')">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Quiz URL')</label>
                                <input type="url" name="quiz_url" class="form-control" maxlength="500"
                                       value="{{ $value('quiz_url') }}" placeholder="{{ rtrim(config('app.url'), '/') }}/quiz/...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Website URL')</label>
                                <input type="url" name="website_url" class="form-control" maxlength="500"
                                       value="{{ $value('website_url') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Campaign')</label>
                                <select name="social_campaign_id" class="form-control">
                                    <option value="">@lang('No campaign')</option>
                                    @foreach($campaigns as $campaign)
                                        <option value="{{ $campaign->id }}" @selected($value('social_campaign_id') == $campaign->id)>
                                            {{ $campaign->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Category')</label>
                                <select name="category_id" class="form-control">
                                    <option value="">@lang('None')</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected($value('category_id') == $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Language')</label>
                                <select name="language" class="form-control">
                                    @foreach(['english' => 'English', 'hindi' => 'Hindi'] as $key => $label)
                                        <option value="{{ $key }}" @selected($value('language', 'english') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Content type')</label>
                                <select name="content_type" class="form-control" id="contentType">
                                    @foreach(S::CONTENT_TYPES as $key => $label)
                                        <option value="{{ $key }}" @selected($value('content_type', 'text') === $key)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                                <small class="social-counter">@lang('Set automatically from the media you attach; override it here if needed.')</small>
                            </div>
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="utm_enabled" value="1" id="utmEnabled"
                                   @checked($editing ? $post->utm_enabled : old('utm_enabled', true))>
                            <label class="form-check-label" for="utmEnabled">
                                @lang('Add UTM tracking to Quiz Mitra links')
                                <small class="d-block social-counter">
                                    @lang('utm_source is set per platform, utm_medium to') <code>{{ $settings->utm_medium }}</code>.
                                    @lang('Only links to this site are tagged.')
                                </small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ------------------------------------------------------- Media --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">@lang('Media')</h6>
                        @if($can[SocialPermission::MANAGE_MEDIA] ?? false)
                            <label class="btn btn-sm btn-outline--primary mb-0">
                                <i class="las la-upload"></i> @lang('Upload')
                                <input type="file" id="quickUpload" multiple hidden
                                       accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm">
                            </label>
                        @endif
                    </div>
                    <div class="card-body">
                        <div id="uploadProgress" class="mb-3 d-none">
                            <div class="social-skeleton mb-2"></div>
                            <small class="social-counter">@lang('Uploading and checking files…')</small>
                        </div>
                        <div id="uploadErrors"></div>

                        <label class="form-label">@lang('Post media')</label>
                        <div class="social-media-grid mb-3" id="mediaGrid">
                            @foreach($mediaLibrary as $media)
                                <div class="social-media-item {{ in_array($media->id, $selectedMedia) ? 'is-selected' : '' }}"
                                     data-media-id="{{ $media->id }}"
                                     data-type="{{ $media->type }}"
                                     data-role="primary"
                                     data-name="{{ e($media->original_name) }}">
                                    @if($media->thumbnail_url)
                                        <img src="{{ $media->thumbnail_url }}" alt="{{ e($media->original_name) }}" loading="lazy">
                                    @else
                                        <div class="social-media-placeholder"><i class="las la-file-video"></i></div>
                                    @endif
                                    <span class="social-media-item__type">{{ $media->type === 'video' ? 'VIDEO' : $media->extension }}</span>
                                    <span class="social-media-item__meta">{{ $media->original_name }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if($mediaLibrary->isEmpty())
                            <p class="social-counter">@lang('Your media library is empty. Upload an image or video to attach it here.')</p>
                        @endif

                        <label class="form-label mt-2">@lang('Custom thumbnail')
                            <small class="social-counter d-block">@lang('Used by YouTube and as a Reel cover where supported.')</small>
                        </label>
                        <div class="social-media-grid" id="thumbGrid">
                            @foreach($mediaLibrary->where('type', 'image')->take(12) as $media)
                                <div class="social-media-item {{ in_array($media->id, $selectedThumb) ? 'is-selected' : '' }}"
                                     data-media-id="{{ $media->id }}" data-role="thumbnail" data-type="image">
                                    <img src="{{ $media->thumbnail_url }}" alt="" loading="lazy">
                                </div>
                            @endforeach
                        </div>

                        <div id="mediaInputs"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn--primary" data-goto="2">@lang('Choose platforms') <i class="las la-arrow-right"></i></button>
        </div>
    </div>

    {{-- ============================================= STEP 2 - platform pick --}}
    <div class="social-pane" data-pane="2">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">@lang('Where should this go?')</h6>
            </div>
            <div class="card-body">
                <div class="social-select-grid" id="platformGrid">
                    @foreach($platforms as $key => $platform)
                        <label class="social-select {{ $platform['available'] ? '' : 'is-disabled' }} {{ in_array($key, $selected) ? 'is-checked' : '' }}"
                               data-platform="{{ $key }}">
                            <input type="checkbox" name="platforms[]" value="{{ $key }}"
                                   {{ $platform['available'] ? '' : 'disabled' }}
                                   @checked(in_array($key, old('platforms', $selected)))>
                            <div class="social-platform__icon" style="background: {{ $platform['color'] }}">
                                <i class="{{ $platform['icon'] }}"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="social-platform__name">{{ $platform['name'] }}</div>
                                @if($platform['available'])
                                    <select name="accounts[{{ $key }}]" class="form-control form-control-sm mt-1"
                                            onclick="event.preventDefault(); event.stopPropagation();">
                                        @foreach($platform['accounts'] as $account)
                                            <option value="{{ $account['id'] }}"
                                                @selected(($existing[$key]->social_account_id ?? null) == $account['id'])>
                                                {{ $account['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="social-select__reason">{{ $platform['reason'] }}</span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>

                <div id="platformWarnings" class="mt-4"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn--dark" data-goto="1"><i class="las la-arrow-left"></i> @lang('Back')</button>
            <button type="button" class="btn btn--primary" data-goto="3">@lang('Customise each platform') <i class="las la-arrow-right"></i></button>
        </div>
    </div>

    {{-- ============================================== STEP 3 - customisation --}}
    <div class="social-pane" data-pane="3">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">@lang('Platform versions')</h6>
                <small class="social-counter">
                    @lang('Each platform starts from your master content, adapted to its own rules. Edit anything here - what you save is what gets published.')
                </small>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="customTabs" role="tablist"></ul>
                <div class="tab-content">
                    @foreach($platforms as $key => $platform)
                        @php
                            $caps    = PlatformCapability::for($key);
                            $payload = $existing[$key]->payload ?? [];
                            $prefix  = "customisations[$key]";
                        @endphp
                        <div class="tab-pane fade" id="tab-{{ $key }}" data-platform-pane="{{ $key }}">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="social-platform__icon" style="background: {{ $platform['color'] }}; width:32px;height:32px;font-size:16px;">
                                    <i class="{{ $platform['icon'] }}"></i>
                                </div>
                                <strong>{{ $platform['name'] }}</strong>
                            </div>

                            @if(PlatformCapability::supports($key, 'title'))
                                <div class="form-group mb-3">
                                    <label class="form-label">@lang('Title')</label>
                                    <input type="text" name="{{ $prefix }}[title]" class="form-control platform-field"
                                           data-source="title" data-platform="{{ $key }}"
                                           maxlength="{{ $caps['limits']['title_max'] ?? 200 }}"
                                           value="{{ old("customisations.$key.title", $payload['title'] ?? '') }}">
                                    <small class="social-counter" data-counter-for="{{ $prefix }}[title]" data-max="{{ $caps['limits']['title_max'] ?? 200 }}"></small>
                                </div>
                            @endif

                            <div class="form-group mb-3">
                                <label class="form-label">@lang('Caption')</label>
                                <textarea name="{{ $prefix }}[caption]" class="form-control platform-field" rows="4"
                                          data-source="caption" data-platform="{{ $key }}"
                                >{{ old("customisations.$key.caption", $payload['caption'] ?? '') }}</textarea>
                                <small class="social-counter" data-counter-for="{{ $prefix }}[caption]" data-max="{{ $caps['limits']['caption_max'] ?? 2000 }}"></small>
                                @if($key === S::X)
                                    <small class="d-block social-counter">
                                        @lang('Longer copy is split into a numbered thread rather than being cut off.')
                                    </small>
                                @endif
                                @if($key === S::INSTAGRAM)
                                    <small class="d-block social-counter">
                                        @lang('Instagram captions do not turn URLs into links - put the link in the first comment instead.')
                                    </small>
                                @endif
                            </div>

                            @if($key === S::YOUTUBE)
                                <div class="form-group mb-3">
                                    <label class="form-label">@lang('Description')</label>
                                    <textarea name="{{ $prefix }}[description]" class="form-control" rows="5"
                                    >{{ old("customisations.$key.description", $payload['description'] ?? '') }}</textarea>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">@lang('Visibility')</label>
                                        <select name="{{ $prefix }}[visibility]" class="form-control">
                                            @foreach(['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'] as $v => $l)
                                                <option value="{{ $v }}" @selected(($payload['visibility'] ?? 'public') === $v)>@lang($l)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">@lang('Category ID')</label>
                                        <input type="text" name="{{ $prefix }}[category_id]" class="form-control"
                                               value="{{ old("customisations.$key.category_id", $payload['category_id'] ?? '') }}"
                                               placeholder="27">
                                        <small class="social-counter">@lang('27 = Education, 24 = Entertainment')</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">@lang('Playlist ID')</label>
                                        <input type="text" name="{{ $prefix }}[playlist_id]" class="form-control"
                                               value="{{ old("customisations.$key.playlist_id", $payload['playlist_id'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="{{ $prefix }}[as_short]" value="1"
                                           id="ytShort" @checked($payload['as_short'] ?? false)>
                                    <label class="form-check-label" for="ytShort">
                                        @lang('Publish as a Short')
                                        <small class="d-block social-counter">
                                            @lang('YouTube decides this from the aspect ratio and duration; ticking this adds the #Shorts marker and checks the limits for you.')
                                        </small>
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="{{ $prefix }}[made_for_kids]" value="1"
                                           id="ytKids" @checked($payload['made_for_kids'] ?? false)>
                                    <label class="form-check-label" for="ytKids">@lang('Made for kids')</label>
                                </div>
                            @endif

                            @if($key === S::FACEBOOK)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="{{ $prefix }}[as_reel]" value="1"
                                           id="fbReel" @checked($payload['as_reel'] ?? false)>
                                    <label class="form-check-label" for="fbReel">@lang('Publish video as a Reel')</label>
                                </div>
                            @endif

                            @if($key === S::INSTAGRAM)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="{{ $prefix }}[share_to_feed]" value="1"
                                           id="igFeed" @checked($payload['share_to_feed'] ?? true)>
                                    <label class="form-check-label" for="igFeed">@lang('Also show the Reel in the main feed')</label>
                                </div>
                            @endif

                            @if(PlatformCapability::supports($key, 'first_comment'))
                                <div class="form-group mb-3">
                                    <label class="form-label">@lang('First comment')</label>
                                    <input type="text" name="{{ $prefix }}[first_comment]" class="form-control"
                                           value="{{ old("customisations.$key.first_comment", $payload['first_comment'] ?? '') }}">
                                    <small class="social-counter">@lang('Posted immediately after publishing. If it fails, the post itself still stands.')</small>
                                </div>
                            @endif

                            @if(PlatformCapability::supports($key, 'buttons'))
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">@lang('Button label')</label>
                                        <input type="text" name="{{ $prefix }}[button_text]" class="form-control"
                                               value="{{ old("customisations.$key.button_text", $payload['button_text'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">@lang('Button URL')</label>
                                        <input type="url" name="{{ $prefix }}[button_url]" class="form-control"
                                               value="{{ old("customisations.$key.button_url", $payload['button_url'] ?? '') }}">
                                    </div>
                                </div>
                            @endif

                            @if($key === S::WHATSAPP)
                                <div class="alert alert-info small">
                                    @lang('WhatsApp is messaging, not a feed. Business-initiated messages must use a template Meta has approved, unless the recipients messaged you in the last 24 hours.')
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">@lang('Approved template name')</label>
                                        <input type="text" name="{{ $prefix }}[template_name]" class="form-control"
                                               value="{{ old("customisations.$key.template_name", $payload['template_name'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">@lang('Template language')</label>
                                        <input type="text" name="{{ $prefix }}[template_language]" class="form-control"
                                               value="{{ old("customisations.$key.template_language", $payload['template_language'] ?? 'en_US') }}">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">@lang('Recipients (override)')</label>
                                        <textarea name="{{ $prefix }}[recipients]" class="form-control" rows="2"
                                                  placeholder="919999999999, 918888888888">{{ old("customisations.$key.recipients", is_array($payload['recipients'] ?? null) ? implode(', ', $payload['recipients']) : ($payload['recipients'] ?? '')) }}</textarea>
                                        <small class="social-counter">@lang('Leave blank to use the numbers configured on the account.')</small>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="{{ $prefix }}[allow_freeform]" value="1"
                                                   id="waFree" @checked($payload['allow_freeform'] ?? false)>
                                            <label class="form-check-label" for="waFree">
                                                @lang('These recipients messaged us in the last 24 hours (send free-form text)')
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group">
                                <label class="form-label">@lang('Hashtags for this platform')</label>
                                <input type="text" name="{{ $prefix }}[hashtags]" class="form-control platform-hashtags"
                                       data-platform="{{ $key }}"
                                       value="{{ old("customisations.$key.hashtags", is_array($payload['hashtags'] ?? null) ? implode(' ', $payload['hashtags']) : '') }}">
                                <small class="social-counter">
                                    @lang('Maximum'): {{ $caps['limits']['hashtag_max'] ?? 0 }}
                                </small>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div id="noPlatformsSelected">
                    @include('admin.social.partials.empty', [
                        'icon' => 'las la-hand-pointer',
                        'title' => 'No platforms selected',
                        'message' => 'Go back a step and pick where this post should go.',
                    ])
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn--dark" data-goto="2"><i class="las la-arrow-left"></i> @lang('Back')</button>
            <button type="button" class="btn btn--primary" data-goto="4">@lang('Preview') <i class="las la-arrow-right"></i></button>
        </div>
    </div>

    {{-- ================================================= STEP 4 - previews --}}
    <div class="social-pane" data-pane="4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">@lang('Preview')</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary small mb-4">
                    <i class="las la-info-circle"></i>
                    @lang('These are approximations drawn by this admin panel, not the platforms themselves. Fonts, cropping and link previews will differ from the live post.')
                </div>
                <ul class="nav nav-tabs mb-3" id="previewTabs"></ul>
                <div id="previewArea"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn--dark" data-goto="3"><i class="las la-arrow-left"></i> @lang('Back')</button>
            <button type="button" class="btn btn--primary" data-goto="5">@lang('Schedule or publish') <i class="las la-arrow-right"></i></button>
        </div>
    </div>

    {{-- ============================================ STEP 5 - publish/schedule --}}
    <div class="social-pane" data-pane="5">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">@lang('Ready to go')</h6></div>
                    <div class="card-body">
                        <div id="finalSummary" class="social-note mb-4"></div>
                        <div id="finalWarnings"></div>

                        <div class="form-group mb-4">
                            <label class="form-label">@lang('Publish date and time')</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" id="scheduledAt"
                                   value="{{ old('scheduled_at', $editing && $post->scheduled_at ? $post->scheduled_at->format('Y-m-d\TH:i') : '') }}">
                            <small class="social-counter">
                                @lang('Times are in'): <strong>{{ $settings->timezone() }}</strong>.
                                @lang('Scheduled posts publish in the background - you can close the browser.')
                            </small>
                        </div>

                        @if($settings->require_approval)
                            <div class="alert alert-warning small">
                                <i class="las la-user-check"></i>
                                @lang('Approval is switched on. This post has to be approved before it can be published.')
                            </div>
                        @endif

                        <div class="d-flex flex-wrap gap-2">
                            @if($can[SocialPermission::PUBLISH] ?? false)
                                <button type="button" class="btn btn--success" data-submit="publish">
                                    <i class="las la-paper-plane"></i> @lang('Publish now')
                                </button>
                            @endif
                            @if($can[SocialPermission::SCHEDULE] ?? false)
                                <button type="button" class="btn btn--primary" data-submit="schedule">
                                    <i class="las la-clock"></i> @lang('Schedule')
                                </button>
                            @endif
                            <button type="button" class="btn btn--dark" data-submit="draft">
                                <i class="las la-save"></i> @lang('Save as draft')
                            </button>
                            @if($settings->require_approval)
                                <button type="button" class="btn btn-outline--primary" data-submit="approval">
                                    <i class="las la-user-check"></i> @lang('Submit for approval')
                                </button>
                            @endif
                            <a href="{{ route('admin.social.posts.index') }}" class="btn btn-outline--danger">@lang('Cancel')</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">@lang('What happens next')</h6></div>
                    <div class="card-body">
                        <ol class="ps-3 mb-0" style="line-height:1.9">
                            <li>@lang('A background job is created for each platform.')</li>
                            <li>@lang('Each platform is published independently, so one failure cannot block the others.')</li>
                            <li>@lang('Temporary failures are retried automatically with a growing delay.')</li>
                            <li>@lang('You see a per-platform result, with the exact reason for anything that failed.')</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-start mt-3">
            <button type="button" class="btn btn--dark" data-goto="4"><i class="las la-arrow-left"></i> @lang('Back')</button>
        </div>
    </div>
</form>

{{-- ------------------------------------------------------------ AI modal --}}
@if($aiEnabled)
<div class="modal fade" id="aiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Generate social content')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    @lang('Everything generated here lands in the editable fields. Nothing is published without you.')
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">@lang('What is this about?')</label>
                    <textarea class="form-control" id="aiBody" rows="5"
                              placeholder="@lang('Paste the quiz question, blog text, or a short brief.')"></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">@lang('Language')</label>
                        <select class="form-control" id="aiLanguage">
                            <option value="English">English</option>
                            <option value="Hindi">Hindi</option>
                            <option value="Hinglish">Hinglish</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">@lang('Tone')</label>
                        <select class="form-control" id="aiTone">
                            <option value="energetic but factual">@lang('Energetic but factual')</option>
                            <option value="professional and informative">@lang('Professional')</option>
                            <option value="playful and challenging">@lang('Playful / challenge')</option>
                        </select>
                    </div>
                </div>
                <div id="aiStatus" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                <button type="button" class="btn btn--primary" id="aiRun">
                    <i class="las la-magic"></i> @lang('Generate')
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('script')
<script>
"use strict";
(function ($) {

    var CAPS      = {!! $capabilities !!};
    var SELECTED  = { primary: @json($selectedMedia), thumbnail: @json($selectedThumb) };
    var routes    = {
        validate: '{{ route('admin.social.posts.validate.media') }}',
        preview:  '{{ route('admin.social.posts.preview') }}',
        upload:   '{{ route('admin.social.media.upload') }}',
        ai:       '{{ $aiEnabled ? route('admin.social.ai.generate') : '' }}'
    };
    var csrf = '{{ csrf_token() }}';

    /* ------------------------------------------------------------ Steps */

    function goTo(step) {
        $('.social-pane').removeClass('is-active');
        $('.social-pane[data-pane="' + step + '"]').addClass('is-active');

        $('.social-step').each(function () {
            var n = parseInt($(this).data('step'), 10);
            $(this).toggleClass('is-active', n === step).toggleClass('is-done', n < step);
        });

        if (step === 3) buildCustomTabs();
        if (step === 4) buildPreviews();
        if (step === 5) buildSummary();

        $('html, body').animate({ scrollTop: $('#socialSteps').offset().top - 90 }, 200);
    }

    $(document).on('click', '[data-goto]', function () {
        var next = parseInt($(this).data('goto'), 10);

        // Only guard forward movement; going back is always allowed.
        if (next > 1 && !$('#masterCaption').val().trim()) {
            iziToast.error({ message: '{{ __('Write a caption before continuing.') }}', position: 'topRight' });
            return goTo(1);
        }
        if (next > 2 && selectedPlatforms().length === 0) {
            iziToast.error({ message: '{{ __('Select at least one platform.') }}', position: 'topRight' });
            return goTo(2);
        }

        goTo(next);
    });

    $(document).on('click', '.social-step', function () {
        goTo(parseInt($(this).data('step'), 10));
    });

    /* -------------------------------------------------------- Platforms */

    function selectedPlatforms() {
        return $('#platformGrid input[name="platforms[]"]:checked').map(function () {
            return this.value;
        }).get();
    }

    $(document).on('change', '#platformGrid input[name="platforms[]"]', function () {
        $(this).closest('.social-select').toggleClass('is-checked', this.checked);
        runValidation();
    });

    /* ---------------------------------------------------------- Media */

    function syncMediaInputs() {
        var html = '';
        ['primary', 'thumbnail'].forEach(function (role) {
            SELECTED[role].forEach(function (id) {
                html += '<input type="hidden" name="media[' + role + '][]" value="' + id + '">';
            });
        });
        $('#mediaInputs').html(html);
    }

    $(document).on('click', '.social-media-item', function () {
        var id   = $(this).data('media-id');
        var role = $(this).data('role');
        var type = $(this).data('type');

        if (role === 'thumbnail') {
            // Only one thumbnail makes sense.
            SELECTED.thumbnail = SELECTED.thumbnail[0] === id ? [] : [id];
            $('#thumbGrid .social-media-item').removeClass('is-selected');
            if (SELECTED.thumbnail.length) $(this).addClass('is-selected');
        } else {
            var index = SELECTED.primary.indexOf(id);

            if (index > -1) {
                SELECTED.primary.splice(index, 1);
                $(this).removeClass('is-selected');
            } else {
                // A video is the whole post - mixing it with images is not a
                // thing any platform supports.
                if (type === 'video') {
                    SELECTED.primary = [id];
                    $('#mediaGrid .social-media-item').removeClass('is-selected');
                } else if (hasSelectedVideo()) {
                    SELECTED.primary = [];
                    $('#mediaGrid .social-media-item').removeClass('is-selected');
                }
                SELECTED.primary.push(id);
                $(this).addClass('is-selected');
            }
        }

        syncMediaInputs();
        autoContentType();
        runValidation();
    });

    function hasSelectedVideo() {
        return SELECTED.primary.some(function (id) {
            return $('#mediaGrid .social-media-item[data-media-id="' + id + '"]').data('type') === 'video';
        });
    }

    /** Keeps the content type in step with what is actually attached. */
    function autoContentType() {
        var $type = $('#contentType');
        if (!SELECTED.primary.length) { return; }
        if ($type.data('touched')) { return; }

        $type.val(hasSelectedVideo() ? 'video' : 'image');
    }

    $('#contentType').on('change', function () { $(this).data('touched', true); });

    /* ------------------------------------------------------ Quick upload */

    $('#quickUpload').on('change', function () {
        var files = this.files;
        if (!files.length) return;

        var data = new FormData();
        data.append('_token', csrf);
        for (var i = 0; i < files.length; i++) data.append('files[]', files[i]);

        $('#uploadProgress').removeClass('d-none');
        $('#uploadErrors').empty();

        $.ajax({
            url: routes.upload, type: 'POST', data: data,
            processData: false, contentType: false,
            success: function (res) {
                (res.media || []).forEach(function (m) {
                    var thumb = m.thumb
                        ? '<img src="' + m.thumb + '" alt="">'
                        : '<div class="social-media-placeholder"><i class="las la-file-video"></i></div>';

                    $('#mediaGrid').prepend(
                        '<div class="social-media-item is-selected" data-media-id="' + m.id + '" data-type="' + m.type + '" data-role="primary">'
                        + thumb
                        + '<span class="social-media-item__type">' + (m.type === 'video' ? 'VIDEO' : 'IMG') + '</span>'
                        + '<span class="social-media-item__meta">' + $('<div>').text(m.name).html() + '</span></div>'
                    );
                    SELECTED.primary.push(m.id);
                });

                showUploadErrors(res.errors || []);
                syncMediaInputs();
                autoContentType();
                runValidation();
            },
            error: function (xhr) {
                var res = xhr.responseJSON || {};
                showUploadErrors(res.errors && res.errors.length ? res.errors : ['{{ __('The upload was rejected.') }}']);
            },
            complete: function () {
                $('#uploadProgress').addClass('d-none');
                $('#quickUpload').val('');
            }
        });
    });

    function showUploadErrors(errors) {
        if (!errors.length) return;
        var html = '';
        errors.forEach(function (e) {
            html += '<div class="alert alert-danger py-2 px-3 small mb-2">' + $('<div>').text(e).html() + '</div>';
        });
        $('#uploadErrors').html(html);
    }

    /* --------------------------------------------------- Pre-flight checks */

    var validateTimer = null;

    function runValidation() {
        clearTimeout(validateTimer);

        validateTimer = setTimeout(function () {
            var platforms = selectedPlatforms();
            if (!platforms.length) {
                $('#platformWarnings, #finalWarnings').empty();
                return;
            }

            $.post(routes.validate, {
                _token: csrf,
                platforms: platforms,
                media: SELECTED.primary,
                caption: $('#masterCaption').val(),
                title: $('[name="title"]').val(),
                hashtags: $('#masterHashtags').val(),
                content_type: $('#contentType').val()
            }, function (res) {
                renderIssues(res.issues || []);
            });
        }, 350);
    }

    function renderIssues(issues) {
        if (!issues.length) {
            var ok = '<div class="alert alert-success py-2 px-3 small mb-0">'
                   + '<i class="las la-check-circle"></i> {{ __('No problems found for the selected platforms.') }}</div>';
            $('#platformWarnings, #finalWarnings').html(ok);
            return;
        }

        var html = '';
        issues.forEach(function (issue) {
            var cls = issue.severity === 'error' ? 'danger' : 'warning';
            var ico = issue.severity === 'error' ? 'la-times-circle' : 'la-exclamation-triangle';
            var name = issue.platform && CAPS[issue.platform] ? CAPS[issue.platform].name : '';

            html += '<div class="alert alert-' + cls + ' py-2 px-3 small mb-2">'
                  + '<i class="las ' + ico + '"></i> '
                  + (name ? '<strong>' + name + ':</strong> ' : '')
                  + $('<div>').text(issue.message).html()
                  + '</div>';
        });

        $('#platformWarnings, #finalWarnings').html(html);
    }

    $('#masterCaption, [name="title"], #masterHashtags').on('input', function () { runValidation(); });

    /* ------------------------------------------------- Customisation tabs */

    function buildCustomTabs() {
        var platforms = selectedPlatforms();

        $('#customTabs').empty();
        $('[data-platform-pane]').removeClass('show active').hide();

        if (!platforms.length) {
            $('#noPlatformsSelected').show();
            return;
        }
        $('#noPlatformsSelected').hide();

        platforms.forEach(function (key, i) {
            var cap = CAPS[key] || { name: key, icon: '', color: '#888' };

            $('#customTabs').append(
                '<li class="nav-item"><a class="nav-link' + (i === 0 ? ' active' : '') + '" href="#tab-' + key + '" '
                + 'data-bs-toggle="tab" data-platform-tab="' + key + '">'
                + '<i class="' + cap.icon + '" style="color:' + cap.color + '"></i> ' + cap.name + '</a></li>'
            );

            var $pane = $('[data-platform-pane="' + key + '"]').show();
            if (i === 0) $pane.addClass('show active');

            seedPlatformFields(key, $pane);
        });

        updateCounters();
    }

    /**
     * Fills an untouched platform field from the master content, so every tab
     * arrives with sensible copy rather than an empty box. A field the admin has
     * already edited is left exactly as they left it.
     */
    function seedPlatformFields(key, $pane) {
        var cap     = CAPS[key] || {};
        var limits  = cap.limits || {};
        var caption = $('#masterCaption').val().trim();
        var title   = $('[name="title"]').val().trim();
        var cta     = $('[name="cta"]').val().trim();

        $pane.find('.platform-field').each(function () {
            var $field = $(this);
            if ($field.val().trim() !== '' || $field.data('touched')) return;

            var source = $field.data('source');
            var value  = source === 'title' ? (title || caption) : caption;

            if (source === 'caption' && cta) value = value + '\n\n' + cta;

            var max = source === 'title' ? (limits.title_max || 200) : (limits.caption_max || 2000);
            if (value.length > max) value = value.slice(0, max - 1).trim() + '…';

            $field.val(value);
        });

        var $tags = $pane.find('.platform-hashtags');
        if ($tags.length && !$tags.val().trim() && !$tags.data('touched')) {
            var max  = limits.hashtag_max || 0;
            var tags = ($('#masterHashtags').val() || '').split(/[\s,]+/).filter(Boolean).slice(0, max);
            $tags.val(tags.join(' '));
        }
    }

    $(document).on('input', '.platform-field, .platform-hashtags', function () {
        $(this).data('touched', true);
        updateCounters();
    });

    /* ------------------------------------------------------------ Counters */

    function updateCounters() {
        $('[data-counter-for]').each(function () {
            var $counter = $(this);
            var name     = $counter.data('counter-for');
            var $field   = $('[name="' + name.replace(/"/g, '\\"') + '"]');

            if (!$field.length) return;

            var len = ($field.val() || '').length;
            var max = parseInt($counter.data('max'), 10) || 0;

            $counter.text(max ? len + ' / ' + max : len + ' {{ __('characters') }}');
            $counter.toggleClass('is-over', max > 0 && len > max);
        });
    }

    $(document).on('input', 'input, textarea', updateCounters);

    /* ------------------------------------------------------------ Previews */

    function buildPreviews() {
        var platforms = selectedPlatforms();
        if (!platforms.length) return;

        $('#previewTabs').empty();
        $('#previewArea').html('<div class="social-skeleton" style="height:220px"></div>');

        var thumb = SELECTED.primary.length
            ? $('#mediaGrid .social-media-item[data-media-id="' + SELECTED.primary[0] + '"] img').attr('src')
            : null;

        var panes = {};

        platforms.forEach(function (key, i) {
            var cap    = CAPS[key] || {};
            var $pane  = $('[data-platform-pane="' + key + '"]');
            var text   = $pane.find('[data-source="caption"]').val() || $('#masterCaption').val();
            var tags   = $pane.find('.platform-hashtags').val() || '';
            var title  = $pane.find('[data-source="title"]').val() || '';

            $('#previewTabs').append(
                '<li class="nav-item"><a class="nav-link' + (i === 0 ? ' active' : '') + '" href="#" '
                + 'data-preview-tab="' + key + '"><i class="' + cap.icon + '" style="color:' + cap.color + '"></i> '
                + cap.name + '</a></li>'
            );

            panes[key] = renderPreview(key, cap, title, text, tags, thumb);
        });

        $('#previewArea').html(panes[platforms[0]]);
        $('#previewArea').data('panes', panes);
    }

    function renderPreview(key, cap, title, text, tags, thumb) {
        var body = $('<div>').text(text + (tags ? '\n\n' + tags : '')).html();

        var media = thumb
            ? '<div class="social-preview__media"><img src="' + thumb + '" alt=""></div>'
            : '';

        var head = '<div class="social-preview__head">'
                 + '<div class="social-platform__icon" style="background:' + cap.color + ';width:34px;height:34px;font-size:17px">'
                 + '<i class="' + cap.icon + '"></i></div>'
                 + '<div><div class="social-platform__name">{{ addslashes(gs('site_name') ?: 'Quiz Mitra') }}</div>'
                 + '<div class="social-platform__meta">' + cap.name + ' · {{ __('just now') }}</div></div></div>';

        var titleHtml = title
            ? '<div class="px-3 pb-2"><strong>' + $('<div>').text(title).html() + '</strong></div>'
            : '';

        return '<div class="social-preview">' + head + titleHtml
             + (key === 'youtube' ? media + '<div class="social-preview__body">' + body + '</div>'
                                  : '<div class="social-preview__body">' + body + '</div>' + media)
             + '<div class="social-preview__actions"><i class="las la-heart"></i><i class="las la-comment"></i><i class="las la-share"></i></div>'
             + '</div>'
             + '<p class="social-preview__note">{{ __('Simulated admin preview - not rendered by') }} ' + cap.name + '.</p>';
    }

    $(document).on('click', '[data-preview-tab]', function (e) {
        e.preventDefault();
        $('[data-preview-tab]').removeClass('active');
        $(this).addClass('active');

        var panes = $('#previewArea').data('panes') || {};
        $('#previewArea').html(panes[$(this).data('preview-tab')] || '');
    });

    /* ------------------------------------------------------------- Summary */

    function buildSummary() {
        var platforms = selectedPlatforms();
        var names = platforms.map(function (k) { return (CAPS[k] || {}).name || k; });

        $('#finalSummary').html(
            '<strong>{{ __('You are about to publish this content to') }} ' + platforms.length + ' '
            + (platforms.length === 1 ? '{{ __('platform') }}' : '{{ __('platforms') }}') + ':</strong> '
            + $('<div>').text(names.join(', ')).html() + '.'
            + (SELECTED.primary.length ? ' ' + SELECTED.primary.length + ' {{ __('media file(s) attached.') }}' : ' {{ __('No media attached.') }}')
        );

        runValidation();
    }

    /* -------------------------------------------------------------- Submit */

    var submitting = false;

    $(document).on('click', '[data-submit]', function () {
        if (submitting) return;

        var action = $(this).data('submit');

        if (action === 'schedule' && !$('#scheduledAt').val()) {
            iziToast.error({ message: '{{ __('Choose a date and time to schedule this post.') }}', position: 'topRight' });
            return;
        }

        if (action === 'publish' && !confirm('{{ __('Publish to the selected platforms now?') }}')) {
            return;
        }

        // The button is locked as well as the flag: between the click and the
        // navigation there is time for a second click, and each one would be a
        // separate request.
        submitting = true;
        $('[data-submit]').prop('disabled', true).first().append(' ');
        $(this).html('<i class="las la-spinner la-spin"></i> {{ __('Working…') }}');

        $('#wizardAction').val(action);
        $('#socialWizard').trigger('submit');
    });

    /* ------------------------------------------------------------------ AI */

    @if($aiEnabled)
    $('#aiGenerate').on('click', function () {
        $('#aiBody').val($('#masterCaption').val() || $('[name="title"]').val() || '');
        new bootstrap.Modal(document.getElementById('aiModal')).show();
    });

    $('#aiRun').on('click', function () {
        var platforms = selectedPlatforms();

        if (!platforms.length) {
            $('#aiStatus').html('<div class="alert alert-warning py-2 px-3 small mb-0">{{ __('Select your platforms first so the copy can be written for them.') }}</div>');
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="las la-spinner la-spin"></i> {{ __('Writing…') }}');
        $('#aiStatus').html('<div class="social-skeleton mb-2"></div><div class="social-skeleton" style="width:70%"></div>');

        $.post(routes.ai, {
            _token: csrf,
            platforms: platforms,
            title: $('[name="title"]').val(),
            body: $('#aiBody').val(),
            url: $('[name="quiz_url"]').val() || $('[name="website_url"]').val(),
            language: $('#aiLanguage').val(),
            tone: $('#aiTone').val()
        }).done(function (res) {
            applyGenerated(res.generated, platforms);
            $('#aiStatus').html('<div class="alert alert-success py-2 px-3 small mb-0">'
                + '{{ __('Copy written into every selected platform tab. Review and edit before publishing.') }}</div>');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON || {}).message || '{{ __('The content assistant could not be reached.') }}';
            $('#aiStatus').html('<div class="alert alert-danger py-2 px-3 small mb-0">' + $('<div>').text(msg).html() + '</div>');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="las la-magic"></i> {{ __('Generate') }}');
        });
    });

    function applyGenerated(generated, platforms) {
        if (!generated) return;

        if (generated.hashtags && generated.hashtags.length) {
            $('#masterHashtags').val(generated.hashtags.join(' '));
        }
        if (generated.cta && !$('[name="cta"]').val()) {
            $('[name="cta"]').val(generated.cta);
        }

        platforms.forEach(function (key) {
            var data  = generated[key];
            if (!data) return;

            var $pane = $('[data-platform-pane="' + key + '"]');

            if (data.caption)  $pane.find('[data-source="caption"]').val(data.caption).data('touched', true);
            if (data.title)    $pane.find('[data-source="title"]').val(data.title).data('touched', true);
            if (data.description) $pane.find('[name$="[description]"]').val(data.description);
            if (data.first_comment) $pane.find('[name$="[first_comment]"]').val(data.first_comment);
            if (data.button_text)   $pane.find('[name$="[button_text]"]').val(data.button_text);
            if (data.hashtags && data.hashtags.length) {
                $pane.find('.platform-hashtags').val(data.hashtags.join(' ')).data('touched', true);
            }

            // The master caption is only filled when it is still empty - the
            // admin's own words are never overwritten.
            if (!$('#masterCaption').val().trim() && data.caption) {
                $('#masterCaption').val(data.caption);
            }
        });

        updateCounters();
        runValidation();
    }
    @endif

    /* ---------------------------------------------------------- Hashtags */

    $(document).on('click', '.hashtag-group', function () {
        var existing = ($('#masterHashtags').val() || '').split(/[\s,]+/).filter(Boolean);
        var added    = String($(this).data('tags')).split(/[\s,]+/).filter(Boolean);
        var merged   = existing.concat(added).filter(function (t, i, a) { return a.indexOf(t) === i; });

        $('#masterHashtags').val(merged.join(' '));
        runValidation();
    });

    /* ------------------------------------------------------------- Startup */

    syncMediaInputs();
    updateCounters();
    if (selectedPlatforms().length) runValidation();

})(jQuery);
</script>
@endpush
