<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorLog extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'temperature' => 'float',
        'ph' => 'float',
    ];
}
