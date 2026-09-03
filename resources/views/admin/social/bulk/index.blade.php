@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Services\Social\SocialPermission;
    $canSchedule = SocialPermission::allows(SocialPermission::SCHEDULE);
@endphp

@section('panel')

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">@lang('Upload a CSV')</h6></div>
            <div class="card-body">
                @if($canSchedule)
                    <form action="{{ route('admin.social.bulk.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="form-label">@lang('CSV file')</label>
                            <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                            <small class="social-counter">@lang('Up to 5 MB and 5,000 rows.')</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn--primary"><i class="las la-upload"></i> @lang('Upload and preview')</button>
                            <a href="{{ route('admin.social.bulk.template') }}" class="btn btn-outline--primary">
                                <i class="las la-download"></i> @lang('Template')
                            </a>
                        </div>
                    </form>
                @else
                    <p class="text-muted mb-0">@lang('Your role cannot schedule posts, so bulk import is read-only for you.')</p>
                @endif

                <div class="social-note mt-3">
                    <strong>@lang('Nothing is created until you confirm.')</strong>
                    @lang('The file is parsed and validated first, and you see exactly what will be created - including anything that would fail - before a single post exists.')
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('Columns')</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two mb-0">
                        <thead>
                            <tr><th>@lang('Column')</th><th>@lang('Meaning')</th></tr>
                        </thead>
                        <tbody>
                            @foreach($columns as $column => $meaning)
                                <tr>
                                    <td>
                                        <code>{{ $column }}</code>
                                        @if(in_array($column, $required, true))
                                            <span class="badge badge--danger">@lang('required')</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $meaning }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <small class="social-counter">
                    @lang('Platform keys:')
                    @foreach($platforms as $key => $platform)
                        <code class="{{ $platform['available'] ? '' : 'text-muted' }}">{{ $key }}</code>{{ !$loop->last ? ' · ' : '' }}
                    @endforeach
                    <br>@lang('Greyed-out keys are not configured or have no connected account, and rows using them are flagged in the preview.')
                </small>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('Recent imports')</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two mb-0">
                        <thead>
                            <tr>
                                <th>@lang('File')</th>
                                <th>@lang('Rows')</th>
                                <th>@lang('Created')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($imports as $import)
                                <tr>
                                    <td>
                                        {{ $import->original_name }}
                                        <small class="d-block text-muted">{{ showDateTime($import->created_at, 'd M Y, h:i A') }}</small>
                                    </td>
                                    <td>
                                        <span class="text--success">{{ $import->valid_rows }}</span> /
                                        <span class="text--danger">{{ $import->invalid_rows }}</span>
                                        <small class="d-block text-muted">@lang('valid / invalid')</small>
                                    </td>
                                    <td>{{ $import->created_posts }}</td>
                                    <td>
                                        <span class="badge badge--{{ $import->status_class }}">{{ ucfirst($import->status) }}</span>
                                        @if($import->error)
                                            <small class="d-block text--danger">{{ strLimit($import->error, 60) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            @if($import->rows_count)
                                                <a href="{{ route('admin.social.bulk.preview', $import->id) }}" class="btn btn-sm btn-outline--primary">
                                                    <i class="las la-eye"></i>
                                                </a>
                                            @endif
                                            @if($canSchedule)
                                                <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                        data-action="{{ route('admin.social.bulk.delete', $import->id) }}"
                                                        data-question="@lang('Remove this import record? Posts it already created are kept.')">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        @include('admin.social.partials.empty', [
                                            'icon' => 'las la-layer-group',
                                            'title' => 'No imports yet',
                                            'message' => 'Upload a CSV to schedule many posts at once - ten quiz videos across four platforms at different times, for example.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($imports->hasPages())
                <div class="card-footer py-4">{{ paginateLinks($imports) }}</div>
            @endif
        </div>
    </div>
</div>

<x-confirmation-modal />

@endsection
