<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'sales_return_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'return_condition'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sales_return()
    {
        return $this->belongsTo(SalesReturn::class);
    }
}
