<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model {
    use GlobalStatus;
    public function deposit() {
        return $this->hasOne(Deposit::class, 'subscription_id');
    }
    public function plan() {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentStatusBadge(): Attribute {
        return new Attribute(function () {
            $html = '';
            if ($this->status == Status::PAYMENT_SUCCESS) {
                $html = '<span class="badge badge--success">' . trans("Paid") . '</span>';
            } else {
                $html = '<span class="badge badge--danger">' . trans("Not Paid") . '</span>';
            }
            return $html;
        });
    }

}
