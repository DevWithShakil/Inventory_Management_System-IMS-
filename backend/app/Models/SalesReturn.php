<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $guarded = [];

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function return_items() {
        return $this->hasMany(SalesReturnItem::class);
    }

}
