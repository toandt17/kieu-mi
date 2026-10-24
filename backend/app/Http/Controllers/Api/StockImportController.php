<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockImport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockImport::query()
            ->with(['product', 'user'])
            ->latest();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $imports = $query->paginate((int) $request->query('per_page', 30));
        $imports->getCollection()->transform(fn (StockImport $item) => $item->toApiArray());

        return ApiResponse::success($imports);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $import = DB::transaction(function () use ($request, $data) {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $product->increment('stock_qty', $data['quantity']);
            $product->increment('imported_qty', $data['quantity']);

            return StockImport::query()->create([
                ...$data,
                'user_id' => $request->user()->id,
            ]);
        });

        return ApiResponse::success(
            $import->load(['product', 'user'])->toApiArray(),
            'Đã nhập kho',
            201
        );
    }
}
