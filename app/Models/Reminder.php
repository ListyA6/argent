<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'bool',
        'last_sent_date' => 'date',
    ];
}
