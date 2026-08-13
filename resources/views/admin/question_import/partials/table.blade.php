<div class="table-responsive--md table-responsive">
    <table class="table--light style--two table">
        <thead>
            <tr>
                <th>@lang('ID')</th>
                <th>@lang('File')</th>
                <th>@lang('Uploaded By')</th>
                <th>@lang('Total')</th>
                <th>@lang('Valid')</th>
                <th>@lang('Invalid')</th>
                <th>@lang('Duplicate')</th>
                <th>@lang('Imported')</th>
                <th>@lang('Status')</th>
                <th>@lang('Date')</th>
                <th>@lang('Action')</th>
            </tr>
        </thead>
        <tbody>
            @forelse($imports as $import)
                <tr>
                    <td>#{{ $import->id }}</td>
                    <td>
                        <span class="d-block">{{ $import->file_name }}</span>
                        <small class="text-muted text-uppercase">{{ $import->file_type }}</small>
                    </td>
                    <td>{{ $import->admin?->name ?? '-' }}</td>
                    <td>{{ $import->total_records }}</td>
                    <td><span class="badge badge--success">{{ $import->valid_records }}</span></td>
                    <td><span class="badge badge--danger">{{ $import->invalid_records }}</span></td>
                    <td><span class="badge badge--warning">{{ $import->duplicate_records }}</span></td>
                    <td><span class="badge badge--primary">{{ $import->imported_records }}</span></td>
                    <td>@php echo $import->statusBadge; @endphp</td>
                    <td>{{ showDateTime($import->created_at) }}</td>
                    <td>
                        <div class="button--group">
                            <a href="{{ route('admin.question-import.preview', $import->id) }}" class="btn btn--sm btn-outline--primary">
                                @if ($import->isProcessing())
                                    <i class="la la-sync"></i> @lang('Continue')
                                @elseif ($import->isReviewable())
                                    <i class="la la-tasks"></i> @lang('Review')
                                @else
                                    <i class="la la-eye"></i> @lang('View')
                                @endif
                            </a>
                            <button class="btn btn--sm btn-outline--info" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{ route('admin.question-import.preview', $import->id) }}">
                                    <i class="la la-eye"></i> @lang('Preview')
                                </a>
                                @if ($import->invalid_records || $import->duplicate_records || $import->failed_records)
                                    <a class="dropdown-item" href="{{ route('admin.question-import.error.report', $import->id) }}">
                                        <i class="la la-file-download"></i> @lang('Download Error Report')
                                    </a>
                                @endif
                                @if (!$import->isFinished() && $import->status != \App\Models\QuestionImport::STATUS_COMPLETED)
                                    <button class="dropdown-item confirmationBtn"
                                            data-action="{{ route('admin.question-import.cancel', $import->id) }}"
                                            data-question="@lang('Cancel this import? Staged rows are kept but nothing will be imported.')">
                                        <i class="la la-ban"></i> @lang('Cancel')
                                    </button>
                                @endif
                                <button class="dropdown-item confirmationBtn"
                                        data-action="{{ route('admin.question-import.delete', $import->id) }}"
                                        data-question="@lang('Delete this import and its staged rows? Questions already imported into the bank are kept.')">
                                    <i class="la la-trash"></i> @lang('Delete')
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
