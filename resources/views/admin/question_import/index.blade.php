@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">@lang('Import Questions')</h5>
                </div>
                <div class="card-body">
                    <div class="row gy-4">
                        <div class="col-md-5">
                            <h6 class="mb-2">@lang('Step 1 — Get the template')</h6>
                            <p class="text-muted mb-3">
                                @lang('Download the template, fill one question per row, then upload it below. Category and sub-category are matched by name against categories that already exist.')
                            </p>
                            <a href="{{ route('admin.question-import.template') }}" class="btn btn-outline--primary">
                                <i class="las la-download"></i> @lang('Download CSV Template')
                            </a>
                        </div>

                        <div class="col-md-7">
                            <h6 class="mb-2">@lang('Step 2 — Upload your file')</h6>
                            <form action="{{ route('admin.question-import.upload') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group">
                                    <label>@lang('CSV File')</label>
                                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                                    <small class="text-muted">@lang('CSV only, maximum 10 MB. Nothing is written to the question bank until you approve the import.')</small>
                                </div>
                                <button type="submit" class="btn btn--primary w-100">
                                    <i class="las la-upload"></i> @lang('Upload & Validate')
                                </button>
                            </form>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">@lang('Required columns')</h6>
                    <div class="table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Column')</th>
                                    <th>@lang('Required')</th>
                                    <th>@lang('Accepted values')</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>category</td>
                                    <td><span class="badge badge--danger">@lang('Yes')</span></td>
                                    <td>@lang('Name of an existing top-level category')</td>
                                </tr>
                                <tr>
                                    <td>sub_category</td>
                                    <td><span class="badge badge--secondary">@lang('No')</span></td>
                                    <td>@lang('Name of an existing sub-category under that category')</td>
                                </tr>
                                <tr>
                                    <td>question</td>
                                    <td><span class="badge badge--danger">@lang('Yes')</span></td>
                                    <td>@lang('Minimum 10 characters')</td>
                                </tr>
                                <tr>
                                    <td>question_type</td>
                                    <td><span class="badge badge--danger">@lang('Yes')</span></td>
                                    <td><code>mcq_single</code>, <code>mcq_multi</code>, <code>true_false</code></td>
                                </tr>
                                <tr>
                                    <td>option_a … option_d</td>
                                    <td><span class="badge badge--danger">@lang('Yes')</span></td>
                                    <td>@lang('All four for MCQ; A and B only for true_false')</td>
                                </tr>
                                <tr>
                                    <td>correct_answer</td>
                                    <td><span class="badge badge--danger">@lang('Yes')</span></td>
                                    <td>@lang('A single letter A–D, or comma-separated like') <code>A,C</code> @lang('for mcq_multi')</td>
                                </tr>
                                <tr>
                                    <td>explanation</td>
                                    <td><span class="badge badge--secondary">@lang('No')</span></td>
                                    <td>@lang('Up to 5000 characters')</td>
                                </tr>
                                <tr>
                                    <td>difficulty</td>
                                    <td><span class="badge badge--danger">@lang('Yes')</span></td>
                                    <td><code>easy</code>, <code>medium</code>, <code>hard</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">@lang('Recent Imports')</h5>
                    <a href="{{ route('admin.question-import.history') }}" class="btn btn--sm btn-outline--primary">
                        @lang('Full History')
                    </a>
                </div>
                <div class="card-body p-0">
                    @include('admin.question_import.partials.table', ['imports' => $imports])
                </div>
                @if ($imports->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($imports) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection
