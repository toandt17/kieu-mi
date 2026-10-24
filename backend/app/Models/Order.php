<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'code',
        'customer_name',
        'customer_phone',
        'status',
        'total_amount',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function toApiArray(bool $withItems = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'note' => $this->note,
            'user_name' => $this->user?->name,
            'item_count' => $this->relationLoaded('items')
                ? $this->items->count()
                : $this->items()->count(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];

        if ($withItems) {
            $data['items'] = $this->items->map(fn (OrderItem $item) => $item->toApiArray())->values();
        }

        return $data;
    }
}
