<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockImport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->latest();
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $products = $query->paginate((int) $request->query('per_page', 30));
        $products->getCollection()->transform(fn (Product $product) => $product->toApiArray());

        return ApiResponse::success($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $initialQty = (int) ($data['initial_qty'] ?? 0);
        unset($data['initial_qty']);

        $product = DB::transaction(function () use ($request, $data, $initialQty) {
            $data['image_path'] = $this->storeImage($request);
            $data['imported_qty'] = $initialQty;
            $data['stock_qty'] = $initialQty;
            $product = Product::query()->create($data);

            if ($initialQty > 0) {
                StockImport::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'quantity' => $initialQty,
                    'note' => 'Nhập kho khi tạo sản phẩm',
                ]);
            }

            return $product;
        });

        return ApiResponse::success($product->toApiArray(), 'Đã thêm sản phẩm', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success($product->toApiArray());
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, $product->id);
        unset($data['initial_qty']);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $this->storeImage($request);
        }

        $product->update($data);

        return ApiResponse::success($product->fresh()->toApiArray(), 'Đã cập nhật sản phẩm');
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->orderItems()->exists()) {
            $product->update(['is_active' => false]);

            return ApiResponse::success($product->fresh()->toApiArray(), 'Sản phẩm đã có đơn hàng nên được ẩn thay vì xóa.');
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->delete();

        return ApiResponse::success(null, 'Đã xóa sản phẩm');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($ignoreId)],
            'listed_price' => ['required', 'integer', 'min:0'],
            'initial_qty' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('products', 'public');
    }
}
