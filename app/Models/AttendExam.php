<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AttendExam extends Model {
    protected $fillable = [
        'user_id',
        'exam_id',
        'start_time',
        'end_time',
        'is_submit',
        'status',
        'correct_count',
        'incorrect_count',
        'pass_percentage',
        'xp_awarded',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_submit' => 'boolean',
        'status' => 'integer',
        'correct_count' => 'integer',
        'incorrect_count' => 'integer',
        'pass_percentage' => 'integer',
        'xp_awarded' => 'integer',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
    public function exam() {
        return $this->belongsTo(Exam::class);
    }
    public function certificateUser() {
        return $this->hasOne(GetCertificateUser::class, 'attend_exam_id', 'id');
    }
    public function examAnswer() {
        return $this->hasMany(ExamAnswer::class, 'exam_attend_id', 'id');
    }

    public function statusBadge(): Attribute {
        return new Attribute(function () {
            $html = '';
            if ($this->status == Status::EXAM_INITIATE) {
                $html = '<span class="badge badge--dark">' . trans("Initiated") . '</span>';
            } else if ($this->status == Status::WAITING_RESULT) {
                $html = '<span class="badge badge--warning">' . trans("Waiting Result") . '</span>';
            } else if ($this->status == Status::EXAM_COMPLETED) {
                $html = '<span class="badge badge--success">' . trans("Completed") . '</span>';
            }
            return $html;
        });
    }

    public function scopeInitiate($query) {
        return $query->where('status', Status::EXAM_INITIATE);
    }
    public function scopeWaitingResult($query) {
        return $query->where('status', Status::WAITING_RESULT);
    }
    public function scopeCompleted($query) {
        return $query->where('status', Status::EXAM_COMPLETED);
    }
}
