<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
