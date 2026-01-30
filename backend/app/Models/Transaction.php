<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'trx_id',
        'type',
        'customer_id',
        'supplier_id',
        'sale_id',
        'purchase_id',
        'amount',
        'date',
        'payment_method',
        'note',
        'meta_data',
        'created_by'
    ];


    protected $casts = [
        'meta_data' => 'array',
        'date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
