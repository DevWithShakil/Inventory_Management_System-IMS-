<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Relationship with Customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Legacy support (optional, if used elsewhere)
    public function sale_items()
    {
        return $this->hasMany(SaleItem::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Legacy support
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sales_returns()
    {
        return $this->hasMany(SalesReturn::class);
    }
}
