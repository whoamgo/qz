<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class QuizImportRow extends Model {
    const STATUS_PENDING   = 'pending';
    const STATUS_VALID     = 'valid';
    const STATUS_INVALID   = 'invalid';
    const STATUS_DUPLICATE = 'duplicate';
    const STATUS_IMPORTED  = 'imported';
    const STATUS_FAILED    = 'failed';
    const STATUS_REMOVED   = 'removed';

    /** Fields an admin may correct from the preview screen. */
    const EDITABLE_FIELDS = [
        'quiz_title', 'quiz_slug', 'quiz_description', 'category_raw', 'quiz_type',
        'quiz_difficulty', 'time_limit', 'pass_percentage', 'marks_per_correct',
        'negative_marking', 'quiz_status', 'question', 'question_type',
        'option_a', 'option_b', 'option_c', 'option_d',
        'correct_answer', 'explanation', 'question_difficulty',
    ];

    protected $fillable = [
        'import_id', 'row_number', 'quiz_key', 'quiz_title', 'quiz_slug',
        'quiz_description', 'category_raw', 'category_id', 'quiz_type', 'price',
        'quiz_difficulty', 'time_limit', 'pass_percentage', 'marks_per_correct',
        'negative_marking', 'quiz_status', 'question', 'question_type',
        'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer',
        'explanation', 'question_difficulty', 'validation_status',
        'validation_errors', 'duplicate_flag', 'duplicate_reason',
        'duplicate_quiz_id', 'duplicate_question_id', 'quiz_id',
        'bank_question_id', 'processed_at',
    ];

    protected $casts = [
        'validation_errors' => 'array',
        'duplicate_flag'    => 'boolean',
        'processed_at'      => 'datetime',
    ];

    public function import()  { return $this->belongsTo(QuizImport::class, 'import_id'); }
    public function category(){ return $this->belongsTo(Category::class, 'category_id'); }
    public function quiz()    { return $this->belongsTo(Quiz::class, 'quiz_id'); }

    public function optionMap(): array {
        return ['A' => $this->option_a, 'B' => $this->option_b, 'C' => $this->option_c, 'D' => $this->option_d];
    }

    public function errorList(): string {
        return implode('; ', $this->validation_errors ?? []);
    }

    public function editPayload(): array {
        return $this->only(array_merge(['id', 'row_number'], self::EDITABLE_FIELDS));
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
            [$c, $l] = $badges[$this->validation_status] ?? ['secondary', $this->validation_status];
            return '<span class="badge badge--' . $c . '">' . trans($l) . '</span>';
        });
    }
}
