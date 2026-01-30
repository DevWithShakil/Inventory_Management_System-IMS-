<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'points', // নোট: আপনার SaleController এ 'reward_points' ব্যবহার করা হয়েছে, কলামের নাম মিলিয়ে নিবেন।
        'reward_points', // যদি ডাটাবেসে reward_points নাম থাকে তবে এটি অ্যাড করুন
        'total_spent'
    ];

    /**
     * Get the sales for the customer.
     */
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
