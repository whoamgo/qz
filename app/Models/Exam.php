<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model {
    use GlobalStatus;

    const DIFFICULTY_EASY = 'easy';
    const DIFFICULTY_MEDIUM = 'medium';
    const DIFFICULTY_HARD = 'hard';

    const TYPE_FREE = 'free';
    const TYPE_PAID = 'paid';

    protected $casts = [
        'subjects' => 'object',
        'price' => 'decimal:2',
    ];

    public function category() {
        return $this->belongsTo(Category::class);
    }
    public function questions() {
        return $this->hasMany(Question::class);
    }
    public function attendExam() {
        return $this->hasMany(AttendExam::class);
    }

    public function scopeUpcoming($query) {
        return $query->where(function ($q) {
            $q->where('exam_type', Exam::TYPE_PAID)
              ->where('start_at', '>', now());
        })->orWhere(function ($q) {
            $q->where('exam_type', Exam::TYPE_FREE);
        });
    }

    public function scopeRunning($query) {
        $query->where(function ($q) {
            $q->where('exam_type', Exam::TYPE_PAID)
              ->where('start_at', '<=', now())
              ->where('result_published_at', '>=', now());
        })->orWhere(function ($q) {
            $q->where('exam_type', Exam::TYPE_FREE);
        });
    }

    public function scopeFree($query) {
        return $query->where('exam_type', self::TYPE_FREE);
    }

    public function scopePaid($query) {
        return $query->where('exam_type', self::TYPE_PAID);
    }

    public function scopePopular($query) {
        return $query->withCount('attendExam')->orderBy('attend_exam_count', 'desc');
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
            if ($this->exam_type == self::TYPE_FREE) {
                return '<span class="badge badge--primary">' . trans("Free") . '</span>';
            }
            return '<span class="badge badge--dark">' . trans("Paid") . ' - ' . showAmount($this->price) . '</span>';
        });
    }

    public function isFree(): bool {
        return $this->exam_type == self::TYPE_FREE;
    }

    public function isPaid(): bool {
        return $this->exam_type == self::TYPE_PAID;
    }

    // XP Settings Relation
    public function xpSettings() {
        return $this->hasOne(QuizXpSetting::class);
    }
}
