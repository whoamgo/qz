<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Option extends Model {
    use GlobalStatus;
    public function question() {
        return $this->belongsTo(Question::class);
    }
}
