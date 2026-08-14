@extends('admin.layouts.app')
@section('panel')
@php $processing = $import->isProcessing(); @endphp

<div class="row" id="qiRoot" data-processing="{{ $processing ? 1 : 0 }}">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">
                    {{ $import->file_name }} <small class="text-muted">@lang('Import') #{{ $import->id }}</small>
                </h5>
                @php echo $import->statusBadge; @endphp
            </div>
            <div class="card-body">

                <div id="qiProgressWrap" class="@if(!$processing) d-none @endif">
                    @foreach ([['Uploading','qiUpload','bg--success'],['Processing','qiProcess','bg--primary'],['Validating','qiValidate','bg--warning']] as [$label,$id,$cls])
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>@lang($label)</span><span id="{{ $id }}Pct">{{ $id === 'qiUpload' ? '100%' : '0%' }}</span>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar {{ $cls }}" id="{{ $id }}Bar" style="width: {{ $id === 'qiUpload' ? '100' : '0' }}%"></div>
                            </div>
                        </div>
                    @endforeach
                    <p class="text-muted mb-0"><i class="las la-info-circle"></i>
                        @lang('Keep this page open. Nothing is created until you approve.')</p>
                </div>

                @if ($import->error_message)
                    <div class="alert alert-danger" id="qiError"><strong>@lang('Error'):</strong> {{ $import->error_message }}</div>
                @else
                    <div class="alert alert-danger d-none" id="qiError"></div>
                @endif

                <div class="row g-3 text-center mt-1">
                    @foreach ([
                        ['quizzes','Quizzes in file','text--dark',$import->total_quizzes],
                        ['total','Total rows','text--dark',$import->total_records],
                        ['valid','Valid','text--success',$import->valid_records],
                        ['invalid','Invalid','text--danger',$import->invalid_records],
                        ['duplicate','Duplicate','text--warning',$import->duplicate_records],
                        ['created','Created','text--primary',$import->imported_quizzes],
                    ] as [$key,$label,$cls,$val])
                        <div class="col-6 col-md-2">
                            <div class="border rounded py-3">
                                <h4 class="mb-0 {{ $cls }}" data-stat="{{ $key }}">{{ $val }}</h4>
                                <small class="text-muted">@lang($label)</small>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Detailed breakdown of what is staged --}}
                <hr>
                <div class="row g-3">
                    @foreach ([
                        ['Total Rows', $stats['rows'], 'text--dark'],
                        ['Total Quizzes', $stats['quizzes'], 'text--info'],
                        ['Total Questions', $stats['questions'], 'text--success'],
                        ['Invalid Rows', $stats['invalid'], 'text--danger'],
                        ['Duplicate Questions', $stats['duplicates'], 'text--warning'],
                        ['Missing Required Fields', $stats['missingFields'], 'text--danger'],
                    ] as [$label, $value, $cls])
                        <div class="col-6 col-md-4 col-lg-2">
                            <small class="text-muted d-block">@lang($label)</small>
                            <strong class="{{ $cls }}">{{ $value }}</strong>
                        </div>
                    @endforeach

                    <div class="col-6 col-md-4 col-lg-3">
                        <small class="text-muted d-block">@lang('Category IDs')</small>
                        <strong>{{ $stats['categories'] ? implode(', ', $stats['categories']) : '—' }}</strong>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <small class="text-muted d-block">@lang('Sub-category IDs')</small>
                        <strong>{{ $stats['subCategories'] ? implode(', ', $stats['subCategories']) : '—' }}</strong>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex flex-wrap gap-2">
                @if ($import->invalid_records || $import->duplicate_records)
                    <a href="{{ route('admin.quiz-import.error.report', $import->id) }}" class="btn btn-outline--danger">
                        <i class="las la-file-download"></i> @lang('Download Error Report')
                    </a>
                @endif
                @if ($import->canApprove())
                    <form action="{{ route('admin.quiz-import.approve', $import->id) }}" method="POST"
                          onsubmit="return confirm('@lang('Create')  {{ $import->valid_quizzes }} @lang('quiz(zes) from') {{ $import->valid_records }} @lang('valid rows?')');">
                        @csrf
                        <input type="hidden" name="expected_valid" value="{{ $import->valid_records }}">
                        <button type="submit" class="btn btn--success">
                            <i class="las la-check-circle"></i> @lang('Approve & Create') ({{ $import->valid_quizzes }})
                        </button>
                    </form>
                @endif
                @if (!$import->isFinished())
                    <button class="btn btn-outline--dark confirmationBtn"
                            data-action="{{ route('admin.quiz-import.cancel', $import->id) }}"
                            data-question="@lang('Cancel this import?')">
                        <i class="las la-ban"></i> @lang('Cancel')
                    </button>
                @endif
                <a href="{{ route('admin.quiz-import.index') }}" class="btn btn-outline--primary ms-auto">
                    <i class="las la-list"></i> @lang('All Imports')
                </a>
            </div>
        </div>
    </div>

    {{-- Rows grouped by quiz, which is exactly how they will be created --}}
    <div class="col-lg-12 mt-4">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills gap-2">
                    @foreach ([null=>'All','valid'=>'Valid','invalid'=>'Invalid','duplicate'=>'Duplicate','imported'=>'Imported'] as $k=>$l)
                        <li class="nav-item">
                            <a class="nav-link @if($filter==$k) active @endif"
                               href="{{ route('admin.quiz-import.preview',$import->id) }}{{ $k ? '?status='.$k : '' }}">@lang($l)</a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body">
                @forelse ($groups as $key => $rows)
                    @php $head = $rows->first(); @endphp
                    <div class="border rounded mb-4">
                        <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <strong>{{ $head->quiz_title ?: '(untitled quiz)' }}</strong>
                                <div class="text-muted small">
                                    @lang('Category'): {{ $head->category?->name ?? $head->category_raw ?? '-' }}
                                    &middot; {{ $rows->count() }} @lang('questions')
                                    &middot; {{ ucfirst($head->quiz_difficulty ?: 'medium') }}
                                    &middot; {{ ucfirst($head->quiz_status ?: 'draft') }}
                                    @if ($head->quiz_id) &middot; <span class="text--success">@lang('created as quiz') #{{ $head->quiz_id }}</span> @endif
                                </div>
                            </div>
                            <div>
                                <span class="badge badge--success">{{ $rows->where('validation_status','valid')->count() }} @lang('valid')</span>
                                <span class="badge badge--danger">{{ $rows->where('validation_status','invalid')->count() }} @lang('invalid')</span>
                                <span class="badge badge--warning">{{ $rows->where('validation_status','duplicate')->count() }} @lang('dup') </span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table--light style--two mb-0">
                                <thead><tr>
                                    <th>@lang('Row')</th><th>@lang('Question')</th><th>@lang('Type')</th>
                                    <th>@lang('Answer')</th><th>@lang('Status')</th>
                                    @if ($import->isReviewable()) <th>@lang('Action')</th> @endif
                                </tr></thead>
                                <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>{{ $row->row_number }}</td>
                                        <td style="max-width:380px;">
                                            <span class="d-block">{{ \Illuminate\Support\Str::limit($row->question, 90) }}</span>
                                            @if ($row->validation_errors)
                                                <small class="text--danger d-block">{{ $row->errorList() }}</small>
                                            @endif
                                            @if ($row->duplicate_flag)
                                                <small class="text--warning d-block">{{ $row->duplicate_reason }}</small>
                                            @endif
                                        </td>
                                        <td><small>{{ $row->question_type ?? '-' }}</small></td>
                                        <td>{{ $row->correct_answer ?? '-' }}</td>
                                        <td>@php echo $row->statusBadge; @endphp</td>
                                        @if ($import->isReviewable())
                                            <td>
                                                <button class="btn btn--sm btn-outline--danger confirmationBtn"
                                                        data-action="{{ route('admin.quiz-import.row.remove',[$import->id,$row->id]) }}"
                                                        data-question="@lang('Remove row') {{ $row->row_number }}?">
                                                    <i class="la la-times"></i>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center py-4 mb-0">@lang('No rows with this status.')</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<x-confirmation-modal />
@endsection

@push('script')
<script>
    "use strict";
    (function ($) {
        var root = document.getElementById('qiRoot');
        if (!root || root.dataset.processing !== '1') { return; }

        var url = "{{ route('admin.quiz-import.process', $import->id) }}";

        function render(d) {
            var pPct = d.total ? Math.round(d.processed / d.total * 100) : 0;
            var vPct = d.total ? Math.round((d.valid + d.invalid + d.duplicate) / d.total * 100) : 0;
            $('#qiProcessBar').css('width', pPct + '%'); $('#qiProcessPct').text(pPct + '%');
            $('#qiValidateBar').css('width', vPct + '%'); $('#qiValidatePct').text(vPct + '%');
            $('[data-stat=quizzes]').text(d.quizzes);
            $('[data-stat=total]').text(d.total);
            $('[data-stat=valid]').text(d.valid);
            $('[data-stat=invalid]').text(d.invalid);
            $('[data-stat=duplicate]').text(d.duplicate);
        }

        function run() {
            $.post(url, { _token: "{{ csrf_token() }}" })
                .done(function (res) {
                    if (res.data) { render(res.data); }
                    if (!res.success) { $('#qiError').removeClass('d-none').text(res.message || 'Processing failed.'); return; }
                    if (res.done) { window.location.reload(); return; }
                    run();
                })
                .fail(function (xhr) {
                    var m = (xhr.responseJSON && xhr.responseJSON.message) || 'Processing failed.';
                    $('#qiError').removeClass('d-none').text(m);
                });
        }
        run();
    })(jQuery);
</script>
@endpush
