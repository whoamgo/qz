<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Question extends Model {
    use GlobalStatus;
    public function options() {
        return $this->hasMany(Option::class);
    }

    public function result() {
        return $this->belongsTo(Option::class, 'result_option_id', 'id');
    }
}
