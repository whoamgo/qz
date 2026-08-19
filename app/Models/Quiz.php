<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Quiz extends Model {
    use GlobalStatus, SoftDeletes;

    const DIFFICULTY_EASY = 'easy';
    const DIFFICULTY_MEDIUM = 'medium';
    const DIFFICULTY_HARD = 'hard';

    const TYPE_FREE = 'free';
    const TYPE_PAID = 'paid';
    const TYPE_SUBSCRIPTION = 'subscription';

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'title', 'slug', 'description', 'category_id', 'sub_category_id',
        'quiz_type', 'price', 'difficulty', 'total_questions',
        'question_limit', 'time_limit',
        'pass_percentage', 'marks_per_correct', 'negative_marking',
        'randomize_questions', 'randomize_options', 'show_result',
        'show_correct_answers', 'show_explanation', 'status', 'is_popular', 'image',
        'start_at', 'end_at'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'marks_per_correct' => 'decimal:2',
        'negative_marking' => 'decimal:2',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
        'show_result' => 'boolean',
        'show_correct_answers' => 'boolean',
        'show_explanation' => 'boolean',
        'is_popular' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subCategory() {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function questions() {
        return $this->belongsToMany(BankQuestion::class, 'quiz_bank_question', 'quiz_id', 'bank_question_id')
            ->withPivot(['question_order', 'marks'])
            ->orderBy('pivot_question_order', 'asc');
    }

    public function quizQuestions() {
        return $this->hasMany(QuizBankQuestion::class, 'quiz_id')
            ->orderBy('question_order', 'asc');
    }

    public function xpSettings() {
        return $this->hasOne(QuizXpSetting::class, 'quiz_id');
    }

    /**
     * How many questions one attempt should serve.
     *
     * A limit of 0 (or one larger than the bank) means "serve everything",
     * so the value is always clamped to what is actually attached.
     */
    public function effectiveQuestionCount(?int $available = null): int {
        $available = $available ?? $this->questions()->count();
        $limit     = (int) $this->question_limit;

        if ($limit <= 0) {
            return $available;
        }

        return min($limit, $available);
    }

    /** True when an attempt sees only part of the bank. */
    public function isLimited(): bool {
        $limit = (int) $this->question_limit;
        return $limit > 0 && $limit < $this->questions()->count();
    }

    /** Public-site attempts of this quiz. */
    public function attempts() {
        return $this->hasMany(QuizAttempt::class, 'quiz_id');
    }

    public function bookmarks() {
        return $this->hasMany(Bookmark::class, 'quiz_id');
    }

    public function scopeSearchable($query, $fields = null) {
        $search = request('search');
        if ($search) {
            $fields = $fields ?: ['title', 'slug', 'description'];
            $query->where(function ($q) use ($fields, $search) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%$search%");
                }
            });
        }
        return $query;
    }

    public function scopeDraft($query) {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePublished($query) {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeArchived($query) {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /** Quizzes an admin has flagged for the "Most Popular" home slider. */
    public function scopePopular($query) {
        return $query->where('is_popular', true);
    }

    public function difficultyBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::DIFFICULTY_EASY => '<span class="badge badge--success">' . trans("Easy") . '</span>',
                self::DIFFICULTY_MEDIUM => '<span class="badge badge--warning">' . trans("Medium") . '</span>',
                self::DIFFICULTY_HARD => '<span class="badge badge--danger">' . trans("Hard") . '</span>',
            ];
            return $badges[$this->difficulty] ?? $badges[self::DIFFICULTY_MEDIUM];
        });
    }

    public function typeBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::TYPE_FREE => '<span class="badge badge--primary">' . trans("Free") . '</span>',
                self::TYPE_PAID => '<span class="badge badge--dark">' . trans("Paid") . ' - ' . showAmount($this->price) . '</span>',
                self::TYPE_SUBSCRIPTION => '<span class="badge badge--info">' . trans("Subscription") . '</span>',
            ];
            return $badges[$this->quiz_type] ?? $badges[self::TYPE_FREE];
        });
    }

    public function quizStatusBadge(): Attribute {
        return new Attribute(function () {
            $badges = [
                self::STATUS_DRAFT => '<span class="badge badge--warning">' . trans("Draft") . '</span>',
                self::STATUS_PUBLISHED => '<span class="badge badge--success">' . trans("Published") . '</span>',
                self::STATUS_ARCHIVED => '<span class="badge badge--secondary">' . trans("Archived") . '</span>',
            ];
            return $badges[$this->status] ?? $badges[self::STATUS_DRAFT];
        });
    }

    public function isDraft(): bool {
        return $this->status == self::STATUS_DRAFT;
    }

    public function isPublished(): bool {
        return $this->status == self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool {
        return $this->status == self::STATUS_ARCHIVED;
    }

    public function getQuestionsCountAttribute() {
        return $this->questions()->count();
    }
}
