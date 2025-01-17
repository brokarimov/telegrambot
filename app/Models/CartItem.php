<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'food_id',
        'cart_id',
        'count',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function food()
    {
        return $this->belongsTo(Food::class, 'food_id');
    }
}
