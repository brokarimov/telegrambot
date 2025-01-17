<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    protected $fillable = [
        'name'
    ];
    public function orderItems()
    {
        return $this->hasMany(OrderItems::class, 'food_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'food_id');
    }
}
