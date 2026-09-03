@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\SocialPermission;
    $canManage = SocialPermission::allows(SocialPermission::MANAGE_AUTOMATION);
    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
@endphp

@section('panel')

{{-- The emergency stop is the first thing on the page, not buried in a menu. --}}
@if($canManage && $anyActive)
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <i class="las la-exclamation-triangle"></i>
            <strong>@lang('Automations are live.')</strong>
            @lang('If something is posting that should not be, stop everything with one click.')
        </div>
        <form action="{{ route('admin.social.automations.stop.all') }}" method="POST">
            @csrf
            <button class="btn btn--danger btn-sm"
                    onclick="return confirm('{{ __('Disable every automation immediately?') }}')">
                <i class="las la-hand-paper"></i> @lang('Emergency stop')
            </button>
        </form>
    </div>
@endif

<div class="row g-3">
    @forelse($automations as $automation)
        <div class="col-lg-6">
            <div class="social-platform h-100">
                <div class="social-platform__head">
                    <div class="social-platform__icon" style="background: {{ $automation->is_active ? '#28a745' : '#adb5bd' }}">
                        <i class="las la-robot"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="social-platform__name">{{ $automation->name }}</div>
                        <div class="social-platform__meta">
                            {{ $automation->source_name }} · {{ $automation->frequency_name }}
                            @if($automation->run_time) @lang('at') {{ \Carbon\Carbon::parse($automation->run_time)->format('h:i A') }} @endif
                        </div>
                    </div>
                    <span class="badge badge--{{ $automation->is_active ? 'success' : 'secondary' }}">
                        {{ $automation->is_active ? __('Active') : __('Off') }}
                    </span>
                </div>

                <div class="social-platform__body">
                    @if($automation->description)
                        <p class="social-platform__meta">{{ $automation->description }}</p>
                    @endif

                    <div class="d-flex flex-wrap gap-1 mb-2">
                        @foreach((array) $automation->platforms as $platform)
                            <span class="social-chip">
                                <i class="{{ S::platformIcon($platform) }}" style="color: {{ S::platformColor($platform) }}"></i>
                                {{ S::platformName($platform) }}
                            </span>
                        @endforeach
                    </div>

                    <span class="social-chip {{ $automation->auto_publish ? 'social-chip--warn' : 'social-chip--ok' }}">
                        <i class="las la-{{ $automation->auto_publish ? 'paper-plane' : 'user-check' }}"></i>
                        {{ $automation->auto_publish ? __('Publishes automatically') : __('Creates a draft for review') }}
                    </span>

                    <table class="table table-sm mt-3 mb-0">
                        <tr><td>@lang('Last run')</td><td class="text-end">{{ $automation->last_run_at ? diffForHumans($automation->last_run_at) : __('never') }}</td></tr>
                        <tr><td>@lang('Next run')</td><td class="text-end">{{ $automation->next_run_at ? showDateTime($automation->next_run_at, 'd M, h:i A') : '—' }}</td></tr>
                        <tr><td>@lang('Total runs')</td><td class="text-end">{{ $automation->run_count }}</td></tr>
                    </table>

                    @if($automation->last_error)
                        <div class="alert alert-danger py-2 px-3 small mt-2 mb-0">{{ $automation->last_error }}</div>
                    @endif
                </div>

                @if($canManage)
                    <div class="social-platform__foot">
                        <form action="{{ route('admin.social.automations.toggle', $automation->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn--{{ $automation->is_active ? 'dark' : 'success' }}">
                                <i class="las la-power-off"></i> {{ $automation->is_active ? __('Turn off') : __('Turn on') }}
                            </button>
                        </form>
                        <form action="{{ route('admin.social.automations.run', $automation->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline--primary"><i class="las la-play"></i> @lang('Run once')</button>
                        </form>
                        <button class="btn btn-sm btn-outline--dark editAutomation"
                                data-automation="{{ json_encode([
                                    'id'                      => $automation->id,
                                    'name'                    => $automation->name,
                                    'description'             => $automation->description,
                                    'source_type'             => $automation->source_type,
                                    'source_config'           => $automation->source_config ?: [],
                                    'frequency'               => $automation->frequency,
                                    'days_of_week'            => $automation->days_of_week ?: [],
                                    'run_time'                => $automation->run_time ? \Carbon\Carbon::parse($automation->run_time)->format('H:i') : '',
                                    'interval_minutes'        => $automation->interval_minutes,
                                    'timezone'                => $automation->timezone,
                                    'platforms'               => (array) $automation->platforms,
                                    'social_template_id'      => $automation->social_template_id,
                                    'social_hashtag_group_id' => $automation->social_hashtag_group_id,
                                    'social_campaign_id'      => $automation->social_campaign_id,
                                    'auto_publish'            => $automation->auto_publish,
                                ]) }}">
                            <i class="las la-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                data-action="{{ route('admin.social.automations.delete', $automation->id) }}"
                                data-question="@lang('Delete this automation? Posts it already created are kept.')">
                            <i class="las la-trash"></i>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('admin.social.partials.empty', [
                        'icon' => 'las la-robot',
                        'title' => 'No automations yet',
                        'message' => 'Set up a recurring campaign - a daily quiz at 8 PM, a weekly GK post - and let it run on its own.',
                    ])
                </div>
            </div>
        </div>
    @endforelse
</div>

@if($canManage)
<div class="modal fade" id="automationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" id="automationForm" action="{{ route('admin.social.automations.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="automationTitle">@lang('New automation')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">@lang('Name') <span class="text--danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150" placeholder="@lang('Daily Quiz at 8 PM')">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">@lang('Content source')</label>
                        <select name="source_type" class="form-control">
                            @foreach($sourceTypes as $key => $label)
                                <option value="{{ $key }}">@lang($label)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">@lang('Description')</label>
                        <input type="text" name="description" class="form-control" maxlength="1000">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">@lang('Category filter')</label>
                        <select name="source_config[category_id]" class="form-control">
                            <option value="">@lang('Any category')</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">@lang('Frequency')</label>
                        <select name="frequency" class="form-control" id="freqSelect">
                            @foreach($frequencies as $key => $label)
                                <option value="{{ $key }}">@lang($label)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 freq-time">
                        <label class="form-label">@lang('Time of day')</label>
                        <input type="time" name="run_time" class="form-control" value="20:00">
                    </div>

                    <div class="col-md-4 freq-interval d-none">
                        <label class="form-label">@lang('Every N minutes')</label>
                        <input type="number" name="interval_minutes" class="form-control" min="5" max="10080" value="60">
                    </div>

                    <div class="col-12 freq-days d-none">
                        <label class="form-label">@lang('Days of the week')</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($days as $value => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]"
                                           value="{{ $value }}" id="dow-{{ $value }}">
                                    <label class="form-check-label" for="dow-{{ $value }}">@lang($label)</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">@lang('Timezone')</label>
                        <input type="text" name="timezone" class="form-control"
                               value="{{ App\Models\Social\SocialSetting::config()->timezone() }}"
                               placeholder="Asia/Kolkata">
                    </div>

                    <div class="col-12">
                        <label class="form-label">@lang('Platforms') <span class="text--danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($platforms as $key => $platform)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="platforms[]"
                                           value="{{ $key }}" id="auto-{{ $key }}"
                                           {{ $platform['available'] ? '' : 'disabled' }}>
                                    <label class="form-check-label" for="auto-{{ $key }}">
                                        {{ $platform['name'] }}
                                        @unless($platform['available'])
                                            <small class="text-muted d-block">{{ $platform['reason'] }}</small>
                                        @endunless
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">@lang('Caption template')</label>
                        <select name="social_template_id" class="form-control">
                            <option value="">@lang('Use the source content as-is')</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">@lang('Hashtag group')</label>
                        <select name="social_hashtag_group_id" class="form-control">
                            <option value="">@lang('None')</option>
                            @foreach($hashtagGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">@lang('Campaign')</label>
                        <select name="social_campaign_id" class="form-control">
                            <option value="">@lang('None')</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="social-note">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_publish" value="1" id="autoPublish">
                                <label class="form-check-label" for="autoPublish">
                                    <strong>@lang('Publish automatically without review')</strong>
                                    <small class="d-block">
                                        @lang('Leave this off and each run creates a draft for a person to check. With it on, content goes to live accounts unattended - the approval setting still applies if it is switched on.')
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                <button class="btn btn--primary">@lang('Save automation')</button>
            </div>
        </form>
    </div>
</div>
@endif

<x-confirmation-modal />

@endsection

@push('breadcrumb-plugins')
    @if($canManage)
        <button class="btn btn-sm btn-outline--primary" id="newAutomation"><i class="las la-plus"></i> @lang('New Automation')</button>
    @endif
@endpush

@push('script')
<script>
"use strict";
(function ($) {
    var $form = $('#automationForm');

    function applyFrequency() {
        var value = $('#freqSelect').val();
        $('.freq-days').toggleClass('d-none', value !== 'weekly');
        $('.freq-interval').toggleClass('d-none', value !== 'interval');
        $('.freq-time').toggleClass('d-none', value === 'interval');
    }

    $('#freqSelect').on('change', applyFrequency);

    $('#newAutomation').on('click', function () {
        $form[0].reset();
        $form.attr('action', '{{ route('admin.social.automations.store') }}');
        $('#automationTitle').text('{{ __('New automation') }}');
        $form.find('input[type="checkbox"]').prop('checked', false);
        applyFrequency();
        new bootstrap.Modal(document.getElementById('automationModal')).show();
    });

    $(document).on('click', '.editAutomation', function () {
        var data = $(this).data('automation');

        $form[0].reset();
        $form.attr('action', '{{ url('admin/social/automations') }}/' + data.id + '/update');
        $('#automationTitle').text('{{ __('Edit automation') }}');

        ['name', 'description', 'source_type', 'frequency', 'run_time', 'interval_minutes',
         'timezone', 'social_template_id', 'social_hashtag_group_id', 'social_campaign_id'].forEach(function (f) {
            $form.find('[name="' + f + '"]').val(data[f] || '');
        });

        $form.find('[name="source_config[category_id]"]').val((data.source_config || {}).category_id || '');
        $form.find('[name="auto_publish"]').prop('checked', !!data.auto_publish);

        $form.find('input[name="platforms[]"]').prop('checked', false);
        (data.platforms || []).forEach(function (p) {
            $form.find('input[name="platforms[]"][value="' + p + '"]').prop('checked', true);
        });

        $form.find('input[name="days_of_week[]"]').prop('checked', false);
        (data.days_of_week || []).forEach(function (d) {
            $form.find('input[name="days_of_week[]"][value="' + d + '"]').prop('checked', true);
        });

        applyFrequency();
        new bootstrap.Modal(document.getElementById('automationModal')).show();
    });

    applyFrequency();
})(jQuery);
</script>
@endpush
