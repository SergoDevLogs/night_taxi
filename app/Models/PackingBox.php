<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingBox extends Model
{
    protected $fillable = ['order_id', 'box_id', 'order_index'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(PackingContent::class)->orderBy('position');
    }
}
