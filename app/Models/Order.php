<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'sum',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class, 'order_id');
    }
}
