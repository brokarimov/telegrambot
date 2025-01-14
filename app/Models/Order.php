<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'longtitude',
        'latitude',
        'time',
        'status',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class, 'order_id');
    }
}
