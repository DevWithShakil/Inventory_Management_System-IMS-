<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Helper to check validity
    public function isValid()
    {
        if (!$this->status) return false;
        if ($this->expires_at && Carbon::now()->gt($this->expires_at)) return false;
        if ($this->usage_limit > 0 && $this->used_count >= $this->usage_limit) return false;
        return true;
    }
}
