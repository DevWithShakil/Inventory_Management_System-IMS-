<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;
    protected $fillable = [
        'sale_id',
        'customer_id',
        'return_no',
        'date',
        'total_amount',
        'deduction_amount',
        'refund_amount',
        'note',
        'created_by'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function return_items()
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
