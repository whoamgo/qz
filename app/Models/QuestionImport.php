<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionImport extends Model {
    use SoftDeletes;

    const STATUS_UPLOADED          = 'uploaded';
    const STATUS_PROCESSING        = 'processing';
    const STATUS_VALIDATION_FAILED = 'validation_failed';
    const STATUS_READY_FOR_REVIEW  = 'ready_for_review';
    const STATUS_APPROVED          = 'approved';
    const STATUS_COMPLETED         = 'completed';
    const STATUS_FAILED            = 'failed';
    const STATUS_CANCELLED         = 'cancelled';

    protected $fillable = [
        'admin_id', 'file_name', 'stored_file', 'file_type', 'status',
        'total_records', 'processed_records', 'valid_records', 'invalid_records',
        'duplicate_records', 'imported_records', 'failed_records',
        'file_cursor', 'error_message', 'approved_at', 'completed_at',
    ];

    protected $casts = [
        'approved_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function admin() {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function rows() {
        return $this->hasMany(QuestionImportRow::class, 'import_id');
    }

    public function validRows() {
        return $this->rows()->where('validation_status', QuestionImportRow::STATUS_VALID);
    }

    public function scopeSearchable($query, $fields = null) {
        $search = request('search');
        if ($search) {
            $fields = $fields ?: ['file_name'];
            $query->where(function ($q) use ($fields, $search) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%$search%");
                }
            });
        }
        return $query;
    }

    /** Processing has finished but the admin has not yet approved or cancelled. */
    public function isReviewable(): bool {
        return $this->status == self::STATUS_READY_FOR_REVIEW;
    }

    /** More rows remain to be read out of the file. */
    public function isProcessing(): bool {
        return in_array($this->status, [self::STATUS_UPLOADED, self::STATUS_PROCESSING]);
    }

    /** Terminal states: nothing further will happen to this import. */
    public function isFinished(): bool {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_VALIDATION_FAILED,
        ]);
    }

    public function canApprove(): bool {
        return $this->isReviewable() && $this->valid_records > 0;
    }

    public function progressPercent(): int {
        if (!$this->total_records) {
            return $this->isProcessing() ? 0 : 100;
        }
        return (int) min(100, round(($this->processed_records / $this->total_records) * 100));
    }

    public function statusBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::STATUS_UPLOADED          => ['secondary', 'Uploaded'],
                self::STATUS_PROCESSING        => ['info', 'Processing'],
                self::STATUS_VALIDATION_FAILED => ['danger', 'Validation Failed'],
                self::STATUS_READY_FOR_REVIEW  => ['warning', 'Ready for Review'],
                self::STATUS_APPROVED          => ['primary', 'Approved'],
                self::STATUS_COMPLETED         => ['success', 'Completed'],
                self::STATUS_FAILED            => ['danger', 'Failed'],
                self::STATUS_CANCELLED         => ['dark', 'Cancelled'],
            ];
            [$class, $label] = $badges[$this->status] ?? ['secondary', $this->status];
            return '<span class="badge badge--' . $class . '">' . trans($label) . '</span>';
        });
    }
}
