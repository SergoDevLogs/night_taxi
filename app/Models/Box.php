<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $fillable = [
        'name',
        'inner_height',
        'inner_length',
        'inner_width',
        'max_weight',
        'available_quantity',
    ];

    protected $casts = [
        'inner_height'       => 'integer',
        'inner_length'       => 'integer',
        'inner_width'        => 'integer',
        'max_weight'         => 'integer',
        'available_quantity' => 'integer',
        'volume'             => 'integer',
        'biggest_side'       => 'integer',
    ];
}
