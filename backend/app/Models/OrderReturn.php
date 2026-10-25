<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    protected $fillable = [
        'code',
        'order_id',
        'reason',
        'note',
        'total_amount',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function toApiArray(bool $withItems = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'order_id' => $this->order_id,
            'order_code' => $this->order?->code,
            'customer_name' => $this->order?->customer_name,
            'customer_phone' => $this->order?->customer_phone,
            'customer_address' => $this->order?->customer_address,
            'shipper_address' => $this->order?->shipper_address,
            'reason' => $this->reason,
            'note' => $this->note,
            'total_amount' => $this->total_amount,
            'user_name' => $this->user?->name,
            'item_count' => $this->relationLoaded('items')
                ? $this->items->count()
                : $this->items()->count(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];

        if ($withItems) {
            $data['items'] = $this->items->map(fn (OrderReturnItem $item) => $item->toApiArray())->values();
        }

        return $data;
    }
}
