<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class QuestionImportRow extends Model {
    const STATUS_PENDING   = 'pending';
    const STATUS_VALID     = 'valid';
    const STATUS_INVALID   = 'invalid';
    const STATUS_DUPLICATE = 'duplicate';
    const STATUS_IMPORTED  = 'imported';
    const STATUS_FAILED    = 'failed';
    const STATUS_REMOVED   = 'removed';

    protected $fillable = [
        'import_id', 'row_number', 'category_name', 'sub_category_name',
        'category_id', 'sub_category_id', 'question', 'question_type',
        'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer',
        'explanation', 'difficulty', 'validation_status', 'validation_errors',
        'duplicate_flag', 'duplicate_question_id', 'bank_question_id', 'processed_at',
    ];

    protected $casts = [
        'validation_errors' => 'array',
        'duplicate_flag'    => 'boolean',
        'processed_at'      => 'datetime',
    ];

    public function import() {
        return $this->belongsTo(QuestionImport::class, 'import_id');
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subCategory() {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function duplicateOf() {
        return $this->belongsTo(BankQuestion::class, 'duplicate_question_id');
    }

    public function scopeValid($query) {
        return $query->where('validation_status', self::STATUS_VALID);
    }

    public function scopeInvalid($query) {
        return $query->where('validation_status', self::STATUS_INVALID);
    }

    public function scopeDuplicate($query) {
        return $query->where('validation_status', self::STATUS_DUPLICATE);
    }

    /**
     * Fields an admin may correct from the preview screen. Shared by the edit
     * form, the controller's validation and the modal payload so the three
     * cannot drift apart.
     */
    const EDITABLE_FIELDS = [
        'category_name', 'sub_category_name', 'question', 'question_type',
        'option_a', 'option_b', 'option_c', 'option_d',
        'correct_answer', 'explanation', 'difficulty',
    ];

    /** Payload used to populate the edit modal. */
    public function editPayload(): array {
        return $this->only(array_merge(['id', 'row_number'], self::EDITABLE_FIELDS));
    }

    /** Options in A-D order, used both for preview display and for promotion. */
    public function optionMap(): array {
        return [
            'A' => $this->option_a,
            'B' => $this->option_b,
            'C' => $this->option_c,
            'D' => $this->option_d,
        ];
    }

    public function errorList(): string {
        return implode('; ', $this->validation_errors ?? []);
    }

    public function statusBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::STATUS_PENDING   => ['secondary', 'Pending'],
                self::STATUS_VALID     => ['success', 'Valid'],
                self::STATUS_INVALID   => ['danger', 'Invalid'],
                self::STATUS_DUPLICATE => ['warning', 'Duplicate'],
                self::STATUS_IMPORTED  => ['primary', 'Imported'],
                self::STATUS_FAILED    => ['danger', 'Failed'],
                self::STATUS_REMOVED   => ['dark', 'Removed'],
            ];
            [$class, $label] = $badges[$this->validation_status] ?? ['secondary', $this->validation_status];
            return '<span class="badge badge--' . $class . '">' . trans($label) . '</span>';
        });
    }
}
