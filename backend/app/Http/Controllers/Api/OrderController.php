<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with('user')->withCount('items')->latest();

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $orders = $query->paginate((int) $request->query('per_page', 20));
        $orders->getCollection()->transform(fn (Order $order) => $order->toApiArray());

        return ApiResponse::success($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['items.product', 'user']);

        return ApiResponse::success($order->toApiArray(true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.sale_price' => ['required', 'integer', 'min:0'],
        ]);

        $order = DB::transaction(function () use ($request, $data) {
            $items = [];
            $total = 0;

            foreach ($data['items'] as $row) {
                $product = Product::query()->lockForUpdate()->findOrFail($row['product_id']);
                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ["Sản phẩm {$product->name} đang bị ẩn."],
                    ]);
                }
                if ($product->stock_qty < $row['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["{$product->name} chỉ còn {$product->stock_qty} sản phẩm."],
                    ]);
                }

                $lineTotal = $row['sale_price'] * $row['quantity'];
                $items[] = [
                    'product' => $product,
                    'quantity' => $row['quantity'],
                    'sale_price' => $row['sale_price'],
                    'line_total' => $lineTotal,
                ];
                $total += $lineTotal;
            }

            $order = Order::query()->create([
                'code' => $this->nextCode(),
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => 'completed',
                'total_amount' => $total,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            foreach ($items as $row) {
                /** @var Product $product */
                $product = $row['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'listed_price' => $product->listed_price,
                    'sale_price' => $row['sale_price'],
                    'quantity' => $row['quantity'],
                    'line_total' => $row['line_total'],
                ]);
                $product->decrement('stock_qty', $row['quantity']);
                $product->increment('sold_qty', $row['quantity']);
            }

            return $order->load(['items.product', 'user']);
        });

        return ApiResponse::success($order->toApiArray(true), 'Đã tạo đơn hàng', 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->status === 'cancelled') {
            return ApiResponse::error('Đơn hàng đã hủy trước đó.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->load('items');
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $product = Product::query()->lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->increment('stock_qty', $item->quantity);
                        $product->decrement('sold_qty', min($product->sold_qty, $item->quantity));
                    }
                }
            }
            $order->update(['status' => 'cancelled']);
        });

        return ApiResponse::success(
            $order->fresh(['items.product', 'user'])->toApiArray(true),
            'Đã hủy đơn và hoàn kho'
        );
    }

    private function nextCode(): string
    {
        $prefix = 'DH'.now()->format('ymd');
        $count = Order::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
