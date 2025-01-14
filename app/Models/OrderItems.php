<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = [
        'food_id',
        'order_id',

    ];

    public function foods()
    {
        return $this->belongsTo(Food::class, 'food_id');
    }
    public function orders()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
