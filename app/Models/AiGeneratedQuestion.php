<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AiGeneratedQuestion extends Model {
    const STATUS_GENERATED      = 'generated';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_APPROVED       = 'approved';
    const STATUS_REJECTED       = 'rejected';
    const STATUS_DUPLICATE      = 'duplicate';
    const STATUS_PUBLISHED      = 'published';

    protected $fillable = [
        'generation_id', 'question', 'options', 'correct_answer', 'explanation',
        'difficulty', 'question_type', 'status', 'duplicate_flag',
        'duplicate_question_id', 'duplicate_generated_id', 'similarity_score',
        'duplicate_overridden', 'question_id', 'validation_errors',
        'review_notes', 'reviewed_by', 'reviewed_at', 'sort_order',
    ];

    protected $casts = [
        'options'              => 'array',
        'duplicate_flag'       => 'boolean',
        'duplicate_overridden' => 'boolean',
        'reviewed_at'          => 'datetime',
    ];

    public function generation() {
        return $this->belongsTo(AiQuestionGeneration::class, 'generation_id');
    }

    public function bankQuestion() {
        return $this->belongsTo(BankQuestion::class, 'question_id');
    }

    public function duplicateOf() {
        return $this->belongsTo(BankQuestion::class, 'duplicate_question_id');
    }

    public function reviewer() {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopeApproved($query) {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePublished($query) {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** Already promoted into the question bank; must not be promoted twice. */
    public function isPublished(): bool {
        return $this->status === self::STATUS_PUBLISHED && $this->question_id !== null;
    }

    /** A flagged duplicate blocks approval unless the admin overrode it. */
    public function blockedByDuplicate(): bool {
        return $this->duplicate_flag && !$this->duplicate_overridden;
    }

    public function optionList(): array {
        return $this->options ?? [];
    }

    /**
     * Payload for the review screen's edit modal. Kept as a method so the
     * Blade call stays on one line - a multi-line argument inside @json()
     * breaks Blade's directive parser.
     */
    public function editPayload(): array {
        return [
            'id'             => $this->id,
            'sort_order'     => $this->sort_order,
            'question'       => $this->question,
            'options'        => $this->optionList(),
            'correct_answer' => $this->correct_answer,
            'explanation'    => $this->explanation,
            'difficulty'     => $this->difficulty,
            'question_type'  => $this->question_type,
        ];
    }

    public function correctOptionText(): ?string {
        return $this->optionList()[$this->correct_answer] ?? null;
    }

    public function statusBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::STATUS_GENERATED      => ['secondary', 'Generated'],
                self::STATUS_PENDING_REVIEW => ['info', 'Pending Review'],
                self::STATUS_APPROVED       => ['primary', 'Approved'],
                self::STATUS_REJECTED       => ['danger', 'Rejected'],
                self::STATUS_DUPLICATE      => ['warning', 'Duplicate'],
                self::STATUS_PUBLISHED      => ['success', 'Published'],
            ];
            [$class, $label] = $badges[$this->status] ?? ['secondary', $this->status];
            return '<span class="badge badge--' . $class . '">' . trans($label) . '</span>';
        });
    }
}
