<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name', 
        'chat_id',
        'status',
        'logo', 
        'longitude',
        'latitude',
        'email',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }
}
