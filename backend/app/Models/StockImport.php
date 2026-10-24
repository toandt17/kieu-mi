<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockImport extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'quantity',
        'unit_cost',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'product_image_url' => $this->product?->imageUrl(),
            'product_stock_qty' => $this->product?->stock_qty,
            'product_listed_price' => $this->product?->listed_price,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'line_cost' => $this->unit_cost ? $this->unit_cost * $this->quantity : null,
            'note' => $this->note,
            'user_name' => $this->user?->name,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
