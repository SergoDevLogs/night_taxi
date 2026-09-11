<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['number', 'status'];

    protected $casts = [
        'status' => OrderStatus::class,
    ];

    /**
     * Виртуальное поле для передачи альтернатив в PackingResultResource.
     * НЕ хранится в БД.
     */
    public array $alternatives = [];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function packingBoxes(): HasMany
    {
        return $this->hasMany(PackingBox::class)->orderBy('order_index');
    }

    public function unpackedItems(): HasMany
    {
        return $this->hasMany(UnpackedItem::class);
    }
}
