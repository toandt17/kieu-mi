<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'listed_price',
        'sale_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'listed_price' => 'integer',
            'sale_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_image_url' => $this->product?->imageUrl(),
            'listed_price' => $this->listed_price,
            'sale_price' => $this->sale_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
        ];
    }
}
