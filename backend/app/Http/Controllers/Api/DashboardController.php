<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $sold = Order::query()->whereIn('status', ['completed', 'partial']);

        $todayRevenue = (int) (clone $sold)->where('created_at', '>=', $today)->sum('total_amount')
            - (int) OrderReturn::query()->where('created_at', '>=', $today)->sum('total_amount');
        $monthRevenue = (int) (clone $sold)->where('created_at', '>=', $month)->sum('total_amount')
            - (int) OrderReturn::query()->where('created_at', '>=', $month)->sum('total_amount');
        $todayOrders = (clone $sold)->where('created_at', '>=', $today)->count();
        $monthOrders = (clone $sold)->where('created_at', '>=', $month)->count();
        $todayReturns = OrderReturn::query()->where('created_at', '>=', $today)->count();

        $lowStock = Product::query()
            ->where('is_active', true)
            ->where('stock_qty', '<=', 5)
            ->orderBy('stock_qty')
            ->limit(6)
            ->get()
            ->map(fn (Product $p) => $p->toApiArray())
            ->values();

        $recentOrders = Order::query()
            ->with(['user', 'items.product'])
            ->withCount('items')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => $order->toApiArray())
            ->values();

        return ApiResponse::success([
            'today_revenue' => (int) $todayRevenue,
            'month_revenue' => (int) $monthRevenue,
            'today_orders' => $todayOrders,
            'month_orders' => $monthOrders,
            'today_returns' => $todayReturns,
            'product_count' => Product::query()->where('is_active', true)->count(),
            'user_count' => User::query()->where('is_active', true)->count(),
            'low_stock_count' => Product::query()->where('is_active', true)->where('stock_qty', '<=', 5)->count(),
            'low_stock' => $lowStock,
            'recent_orders' => $recentOrders,
        ]);
    }
}
