<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'listed_price',
        'stock_qty',
        'imported_qty',
        'sold_qty',
        'image_path',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'listed_price' => 'integer',
            'stock_qty' => 'integer',
            'imported_qty' => 'integer',
            'sold_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stockImports(): HasMany
    {
        return $this->hasMany(StockImport::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return '/storage/'.$this->image_path;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'listed_price' => $this->listed_price,
            'stock_qty' => $this->stock_qty,
            'imported_qty' => $this->imported_qty,
            'sold_qty' => $this->sold_qty,
            'image_url' => $this->imageUrl(),
            'note' => $this->note,
            'is_active' => $this->is_active,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
