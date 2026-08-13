<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankQuestion extends Model {
    use GlobalStatus, SoftDeletes;

    const TYPE_MCQ_SINGLE = 'mcq_single';
    const TYPE_MCQ_MULTI = 'mcq_multi';
    const TYPE_TRUE_FALSE = 'true_false';

    const DIFFICULTY_EASY = 'easy';
    const DIFFICULTY_MEDIUM = 'medium';
    const DIFFICULTY_HARD = 'hard';

    protected $fillable = [
        'category_id', 'sub_category_id', 'question_type', 'difficulty',
        'question_text', 'explanation', 'hint', 'default_marks',
        'correct_option_id', 'status'
    ];

    protected $casts = [
        'default_marks' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subCategory() {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function options() {
        return $this->hasMany(BankOption::class, 'bank_question_id')->orderBy('sort_order', 'asc');
    }

    public function correctOption() {
        return $this->belongsTo(BankOption::class, 'correct_option_id');
    }

    public function quizzes() {
        return $this->belongsToMany(Quiz::class, 'quiz_bank_question', 'bank_question_id', 'quiz_id')
            ->withPivot(['question_order', 'marks']);
    }

    public function scopeSearchable($query, $fields = null) {
        $search = request('search');
        if ($search) {
            $fields = $fields ?: ['question_text', 'explanation', 'hint'];
            $query->where(function ($q) use ($fields, $search) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%$search%");
                }
            });
        }
        return $query;
    }

    public function scopeMcqSingle($query) {
        return $query->where('question_type', self::TYPE_MCQ_SINGLE);
    }

    public function scopeMcqMulti($query) {
        return $query->where('question_type', self::TYPE_MCQ_MULTI);
    }

    public function scopeTrueFalse($query) {
        return $query->where('question_type', self::TYPE_TRUE_FALSE);
    }

    public function scopeEasy($query) {
        return $query->where('difficulty', self::DIFFICULTY_EASY);
    }

    public function scopeMedium($query) {
        return $query->where('difficulty', self::DIFFICULTY_MEDIUM);
    }

    public function scopeHard($query) {
        return $query->where('difficulty', self::DIFFICULTY_HARD);
    }

    public function getOptionsCountAttribute() {
        return $this->options()->count();
    }
}
