<?php

namespace App\Models;

use App\Enums\Orientation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingContent extends Model
{
    protected $fillable = [
        'packing_box_id',
        'product_id',
        'quantity',
        'position',
        'orientation',
        'x',
        'y',
        'z',
        'position_hint',
        'step_text',
        'supported_by',
        'neighbors',
        'support_ratio',
        'stability_warning',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'position'      => 'integer',
        'orientation'   => Orientation::class,
        'x'             => 'integer',
        'y'             => 'integer',
        'z'             => 'integer',
        'supported_by'  => 'array',
        'neighbors'     => 'array',
        'support_ratio' => 'float',
    ];

    public function packingBox(): BelongsTo
    {
        return $this->belongsTo(PackingBox::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
