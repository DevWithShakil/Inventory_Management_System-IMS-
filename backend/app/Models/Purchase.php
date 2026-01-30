<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $guarded = []; // Allow mass assignment

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // 🔥 FIX: This was missing, causing the 500 Error
    public function purchase_items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // Alias if you use 'items' elsewhere
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
