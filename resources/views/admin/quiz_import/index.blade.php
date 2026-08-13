@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">@lang('Import Quizzes')</h5></div>
                <div class="card-body">
                    <div class="row gy-4">
                        <div class="col-md-5">
                            <h6 class="mb-2">@lang('Step 1 — Get the template')</h6>
                            <p class="text-muted mb-3">
                                @lang('One row per question. Rows that share the same quiz_slug (or quiz_title) are grouped into a single quiz, so a file can create many quizzes at once.')
                            </p>
                            <a href="{{ route('admin.quiz-import.template') }}" class="btn btn-outline--primary">
                                <i class="las la-download"></i> @lang('Download CSV Template')
                            </a>
                        </div>

                        <div class="col-md-7">
                            <h6 class="mb-2">@lang('Step 2 — Upload your file')</h6>
                            <form action="{{ route('admin.quiz-import.upload') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group">
                                    <label class="form-label">@lang('CSV File')</label>
                                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                                    <small class="text-muted">@lang('CSV only, max 10 MB. Nothing is created until you approve the import.')</small>
                                </div>
                                <button type="submit" class="btn btn--primary w-100">
                                    <i class="las la-upload"></i> @lang('Upload & Validate')
                                </button>
                            </form>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3">@lang('Columns')</h6>
                    <div class="table-responsive">
                        <table class="table table--light style--two">
                            <thead><tr><th>@lang('Column')</th><th>@lang('Required')</th><th>@lang('Notes')</th></tr></thead>
                            <tbody>
                                @foreach ([
                                    ['quiz_title','yes','Rows sharing a title/slug become one quiz'],
                                    ['quiz_slug','no','Auto-generated from the title if blank'],
                                    ['quiz_description','no','Shown on the quiz page'],
                                    ['category_id','yes','Category id, name or slug — must be top-level'],
                                    ['quiz_type','no','free / paid / subscription (default free)'],
                                    ['price','no','Required when quiz_type is paid'],
                                    ['quiz_difficulty','no','easy / medium / hard (default medium)'],
                                    ['time_limit','no','Minutes, 0 = no limit'],
                                    ['pass_percentage','no','0-100'],
                                    ['marks_per_correct','no','Default 1'],
                                    ['negative_marking','no','Default 0'],
                                    ['quiz_status','no','draft / published / archived (default draft)'],
                                    ['question','yes','Minimum 10 characters'],
                                    ['question_type','yes','mcq_single / mcq_multi / true_false'],
                                    ['option_a … option_d','yes','All four for MCQ; A and B only for true_false'],
                                    ['correct_answer','yes','A–D, or "A,C" for mcq_multi'],
                                    ['explanation','no','Up to 5000 characters'],
                                    ['question_difficulty','no','Falls back to quiz_difficulty'],
                                ] as [$col,$req,$note])
                                    <tr>
                                        <td><code>{{ $col }}</code></td>
                                        <td><span class="badge badge--{{ $req === 'yes' ? 'danger' : 'secondary' }}">{{ ucfirst($req) }}</span></td>
                                        <td class="text-muted">{{ $note }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">@lang('Recent Quiz Imports')</h5>
                    <a href="{{ route('admin.quiz-import.history') }}" class="btn btn--sm btn-outline--primary">@lang('Full History')</a>
                </div>
                <div class="card-body p-0">
                    @include('admin.quiz_import.partials.table', ['imports' => $imports])
                </div>
                @if ($imports->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($imports) }}</div>
                @endif
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection
