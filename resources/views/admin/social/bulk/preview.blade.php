@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Services\Social\SocialPermission;
    $canSchedule = SocialPermission::allows(SocialPermission::SCHEDULE);
@endphp

@section('panel')

<div class="social-kpi-grid mb-4">
    @include('admin.social.partials.kpi', ['label' => 'Rows read',   'value' => $import->total_rows,    'icon' => 'las la-list',   'colour' => '#6c757d'])
    @include('admin.social.partials.kpi', ['label' => 'Ready',       'value' => $import->valid_rows,    'icon' => 'las la-check',  'colour' => '#28a745'])
    @include('admin.social.partials.kpi', ['label' => 'With errors', 'value' => $import->invalid_rows,  'icon' => 'las la-times',  'colour' => '#dc3545'])
    @include('admin.social.partials.kpi', ['label' => 'Created',     'value' => $import->created_posts, 'icon' => 'las la-plus',   'colour' => '#0d6efd'])
</div>

@if($import->status !== 'completed' && $canSchedule)
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('admin.social.bulk.confirm', $import->id) }}" method="POST"
                  onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                @csrf
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="mb-1">
                            @lang('Ready to import') <strong>{{ $import->valid_rows }}</strong> @lang('post(s)')
                        </h6>
                        <p class="mb-2 social-counter">
                            @lang('Rows with errors are skipped - they are never guessed at. Fix them in the file and upload again if you need them.')
                        </p>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="schedule" value="1" id="doSchedule" checked>
                            <label class="form-check-label" for="doSchedule">
                                @lang('Also schedule them using the scheduled_at column')
                                <small class="d-block text-muted">@lang('Leave unticked to create everything as drafts.')</small>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn--primary" {{ $import->valid_rows ? '' : 'disabled' }}>
                        <i class="las la-check"></i> @lang('Import now')
                    </button>
                </div>
            </form>
        </div>
    </div>
@elseif($import->status === 'completed')
    <div class="alert alert-success">
        <i class="las la-check-circle"></i>
        @lang('This import has already run and created') <strong>{{ $import->created_posts }}</strong> @lang('post(s).')
        <a href="{{ route('admin.social.posts.index') }}">@lang('View them')</a>.
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">@lang('Row by row')</h6>
        <a href="{{ route('admin.social.bulk.index') }}" class="btn btn-sm btn-outline--dark">
            <i class="las la-arrow-left"></i> @lang('Back')
        </a>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Row')</th>
                        <th>@lang('Caption')</th>
                        <th>@lang('Platforms')</th>
                        <th>@lang('Scheduled')</th>
                        <th>@lang('Media')</th>
                        <th>@lang('Status')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="{{ $row->status === 'invalid' ? 'table-danger' : '' }}">
                            <td>{{ $row->row_number }}</td>
                            <td style="max-width:320px">
                                <div class="text-truncate">{{ $row->data['title'] ?? '' }}</div>
                                <small class="text-muted">{{ strLimit($row->data['caption'] ?? '', 80) }}</small>
                            </td>
                            <td><code class="small">{{ $row->data['platforms'] ?? '' }}</code></td>
                            <td><small>{{ $row->data['scheduled_at'] ?? '—' }}</small></td>
                            <td><small>{{ strLimit($row->data['media'] ?? '—', 30) }}</small></td>
                            <td>
                                <span class="badge badge--{{ $row->status_class }}">{{ ucfirst($row->status) }}</span>
                                @if($row->errors)
                                    <ul class="mb-0 mt-1 ps-3 small text--danger">
                                        @foreach($row->errors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($row->social_post_id)
                                    <a href="{{ route('admin.social.posts.show', $row->social_post_id) }}" class="small d-block mt-1">
                                        @lang('View post') #{{ $row->social_post_id }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($rows->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($rows) }}</div>
    @endif
</div>

@endsection
