@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Models\Social\SocialCampaign;
    use App\Services\Social\SocialPermission;
    $canManage = SocialPermission::allows(SocialPermission::MANAGE_LIBRARY);
@endphp

@section('panel')

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Campaign')</th>
                        <th>@lang('Dates')</th>
                        <th>@lang('Platforms')</th>
                        <th>@lang('Posts')</th>
                        <th>@lang('UTM')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td>
                                <a href="{{ route('admin.social.campaigns.show', $campaign->id) }}">{{ $campaign->name }}</a>
                                @if($campaign->description)
                                    <small class="d-block text-muted">{{ strLimit($campaign->description, 70) }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $campaign->start_date?->format('d M Y') ?: '—' }}
                                &rarr;
                                {{ $campaign->end_date?->format('d M Y') ?: '—' }}
                                @if($campaign->isRunning())
                                    <span class="badge badge--success">@lang('Running')</span>
                                @endif
                            </td>
                            <td>
                                @forelse((array) $campaign->platforms as $platform)
                                    <i class="{{ App\Constants\SocialStatus::platformIcon($platform) }}"
                                       style="color: {{ App\Constants\SocialStatus::platformColor($platform) }}"
                                       title="{{ App\Constants\SocialStatus::platformName($platform) }}"></i>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $campaign->posts_count }}</td>
                            <td><code class="small">{{ $campaign->utm_campaign }}</code></td>
                            <td><span class="badge badge--{{ $campaign->status_class }}">{{ SocialCampaign::STATUSES[$campaign->status] ?? $campaign->status }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.social.campaigns.show', $campaign->id) }}" class="btn btn-sm btn-outline--primary">
                                        <i class="las la-chart-bar"></i>
                                    </a>
                                    @if($canManage)
                                        {{-- Dates are emitted as Y-m-d so they drop straight into
                                             the modal's date inputs without reformatting in JS. --}}
                                        <button class="btn btn-sm btn-outline--dark editCampaign"
                                                data-campaign="{{ json_encode([
                                                    'id'           => $campaign->id,
                                                    'name'         => $campaign->name,
                                                    'description'  => $campaign->description,
                                                    'start_date'   => $campaign->start_date?->toDateString(),
                                                    'end_date'     => $campaign->end_date?->toDateString(),
                                                    'platforms'    => (array) $campaign->platforms,
                                                    'hashtags'     => $campaign->hashtags,
                                                    'utm_campaign' => $campaign->utm_campaign,
                                                    'status'       => $campaign->status,
                                                ]) }}">
                                            <i class="las la-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-action="{{ route('admin.social.campaigns.delete', $campaign->id) }}"
                                                data-question="@lang('Delete this campaign? Its posts are kept and simply lose the campaign tag.')">
                                            <i class="las la-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-bullhorn',
                                    'title' => 'No campaigns yet',
                                    'message' => 'Group related posts under a campaign to track them together and share a UTM tag.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($campaigns->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($campaigns) }}</div>
    @endif
</div>

@if($canManage)
    <div class="modal fade" id="campaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" id="campaignForm" action="{{ route('admin.social.campaigns.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignTitle">@lang('New campaign')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">@lang('Name') <span class="text--danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('Status')</label>
                            <select name="status" class="form-control">
                                @foreach(SocialCampaign::STATUSES as $key => $label)
                                    <option value="{{ $key }}">@lang($label)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">@lang('Description')</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">@lang('Start date')</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">@lang('End date')</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">@lang('Platforms')</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($platforms as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="platforms[]"
                                               value="{{ $key }}" id="camp-{{ $key }}">
                                        <label class="form-check-label" for="camp-{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">@lang('Hashtags')</label>
                            <input type="text" name="hashtags" class="form-control" placeholder="#dailyquiz #gk">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">@lang('UTM campaign')</label>
                            <input type="text" name="utm_campaign" class="form-control" placeholder="daily_quiz_sep_2026">
                            <small class="social-counter">@lang('Letters, numbers, hyphens and underscores. Generated from the name if left blank.')</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button class="btn btn--primary">@lang('Save campaign')</button>
                </div>
            </form>
        </div>
    </div>
@endif

<x-confirmation-modal />

@endsection

@push('breadcrumb-plugins')
    @if($canManage)
        <button class="btn btn-sm btn-outline--primary" id="newCampaign">
            <i class="las la-plus"></i> @lang('New Campaign')
        </button>
    @endif
@endpush

@push('script')
<script>
"use strict";
(function ($) {
    var $modal = $('#campaignModal');
    var $form  = $('#campaignForm');

    $('#newCampaign').on('click', function () {
        $form[0].reset();
        $form.attr('action', '{{ route('admin.social.campaigns.store') }}');
        $('#campaignTitle').text('{{ __('New campaign') }}');
        $form.find('input[name="platforms[]"]').prop('checked', false);
        new bootstrap.Modal($modal[0]).show();
    });

    $(document).on('click', '.editCampaign', function () {
        var data = $(this).data('campaign');

        $form[0].reset();
        $form.attr('action', '{{ url('admin/social/campaigns') }}/' + data.id + '/update');
        $('#campaignTitle').text('{{ __('Edit campaign') }}');

        ['name', 'description', 'start_date', 'end_date', 'hashtags', 'utm_campaign', 'status'].forEach(function (field) {
            $form.find('[name="' + field + '"]').val(data[field] || '');
        });

        $form.find('input[name="platforms[]"]').prop('checked', false);
        (data.platforms || []).forEach(function (p) {
            $form.find('input[name="platforms[]"][value="' + p + '"]').prop('checked', true);
        });

        new bootstrap.Modal($modal[0]).show();
    });
})(jQuery);
</script>
@endpush
