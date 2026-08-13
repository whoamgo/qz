<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiQuestionGeneration extends Model {
    use SoftDeletes;

    const STATUS_PENDING             = 'pending';
    const STATUS_GENERATING          = 'generating';
    const STATUS_COMPLETED           = 'completed';
    const STATUS_FAILED              = 'failed';
    const STATUS_PARTIALLY_COMPLETED = 'partially_completed';
    const STATUS_CANCELLED           = 'cancelled';

    protected $fillable = [
        'category_id', 'sub_category_id', 'quiz_id', 'topic', 'difficulty',
        'question_type', 'language', 'quantity', 'provider', 'model',
        'temperature', 'prompt', 'system_prompt', 'additional_instructions',
        'raw_response', 'status', 'error_message', 'requested_count',
        'generated_count', 'approved_count', 'rejected_count',
        'duplicate_count', 'published_count', 'input_tokens', 'output_tokens',
        'total_tokens', 'estimated_cost', 'created_by',
    ];

    protected $casts = [
        'temperature'    => 'float',
        'estimated_cost' => 'decimal:6',
    ];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subCategory() {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function quiz() {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function creator() {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function questions() {
        return $this->hasMany(AiGeneratedQuestion::class, 'generation_id')->orderBy('sort_order');
    }

    public function approvedQuestions() {
        return $this->questions()->whereIn('status', [
            AiGeneratedQuestion::STATUS_APPROVED,
            AiGeneratedQuestion::STATUS_PUBLISHED,
        ]);
    }

    public function scopeSearchable($query, $fields = null) {
        $search = request('search');
        if ($search) {
            $fields = $fields ?: ['topic'];
            $query->where(function ($q) use ($fields, $search) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%$search%");
                }
            });
        }
        return $query;
    }

    public function isReviewable(): bool {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIALLY_COMPLETED]);
    }

    public function isFailed(): bool {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /** Refreshes the denormalised review counters from the child rows. */
    public function syncCounts(): void {
        $counts = $this->questions()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->generated_count = (int) $this->questions()->count();
        $this->approved_count  = (int) ($counts[AiGeneratedQuestion::STATUS_APPROVED] ?? 0);
        $this->rejected_count  = (int) ($counts[AiGeneratedQuestion::STATUS_REJECTED] ?? 0);
        $this->published_count = (int) ($counts[AiGeneratedQuestion::STATUS_PUBLISHED] ?? 0);
        $this->duplicate_count = (int) $this->questions()->where('duplicate_flag', true)->count();
        $this->save();
    }

    public function statusBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::STATUS_PENDING             => ['secondary', 'Pending'],
                self::STATUS_GENERATING          => ['info', 'Generating'],
                self::STATUS_COMPLETED           => ['success', 'Completed'],
                self::STATUS_FAILED              => ['danger', 'Failed'],
                self::STATUS_PARTIALLY_COMPLETED => ['warning', 'Partially Completed'],
                self::STATUS_CANCELLED           => ['dark', 'Cancelled'],
            ];
            [$class, $label] = $badges[$this->status] ?? ['secondary', $this->status];
            return '<span class="badge badge--' . $class . '">' . trans($label) . '</span>';
        });
    }
}
