<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrderReturn::query()->with('order', 'user')->withCount('items')->latest();

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($order) use ($search) {
                        $order->where('code', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $returns = $query->paginate((int) $request->query('per_page', 30));
        $returns->getCollection()->transform(fn (OrderReturn $item) => $item->toApiArray());

        return ApiResponse::success($returns);
    }

    public function show(OrderReturn $orderReturn): JsonResponse
    {
        $orderReturn->load(['items', 'order', 'user']);

        return ApiResponse::success($orderReturn->toApiArray(true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $ret = DB::transaction(function () use ($request, $data) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($data['order_id']);
            if (! in_array($order->status, ['completed', 'partial'], true)) {
                throw ValidationException::withMessages([
                    'order_id' => ['Chỉ trả hàng cho đơn đã hoàn tất.'],
                ]);
            }

            $rows = [];
            $total = 0;

            foreach ($data['items'] as $row) {
                $item = $order->items->firstWhere('id', (int) $row['order_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => ['Món hàng không thuộc đơn này.'],
                    ]);
                }

                $remain = max(0, $item->quantity - (int) $item->returned_qty);
                if ($row['quantity'] > $remain) {
                    throw ValidationException::withMessages([
                        'items' => ["{$item->product_name} chỉ còn trả được {$remain}."],
                    ]);
                }

                $line = $item->sale_price * $row['quantity'];
                $rows[] = [
                    'item' => $item,
                    'quantity' => $row['quantity'],
                    'line_total' => $line,
                ];
                $total += $line;
            }

            $ret = OrderReturn::query()->create([
                'code' => $this->nextCode(),
                'order_id' => $order->id,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'total_amount' => $total,
                'user_id' => $request->user()->id,
            ]);

            foreach ($rows as $row) {
                /** @var OrderItem $item */
                $item = $row['item'];
                $ret->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $row['quantity'],
                    'sale_price' => $item->sale_price,
                    'line_total' => $row['line_total'],
                ]);
                $item->increment('returned_qty', $row['quantity']);

                if ($item->product_id) {
                    $product = Product::query()->lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->increment('stock_qty', $row['quantity']);
                        $product->decrement('sold_qty', min($product->sold_qty, $row['quantity']));
                    }
                }
            }

            $order->load('items');
            $allReturned = $order->items->every(
                fn (OrderItem $item) => $item->returned_qty >= $item->quantity
            );
            $order->update(['status' => $allReturned ? 'returned' : 'partial']);

            return $ret->load(['items', 'order', 'user']);
        });

        return ApiResponse::success($ret->toApiArray(true), 'Đã tạo phiếu trả hàng', 201);
    }

    private function nextCode(): string
    {
        $prefix = 'TH'.now()->format('ymd');
        $count = OrderReturn::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
