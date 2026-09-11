<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku', 'name', 'height', 'length', 'width', 'weight',
        'can_rotate', 'fragile', 'top_bottom',
    ];

    protected $casts = [
        'height'     => 'integer',
        'length'     => 'integer',
        'width'      => 'integer',
        'weight'     => 'integer',
        'can_rotate' => 'boolean',
        'fragile'    => 'boolean',
        'top_bottom' => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
