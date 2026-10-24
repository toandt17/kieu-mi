<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

        $completed = Order::query()->where('status', 'completed');

        $todayRevenue = (clone $completed)->where('created_at', '>=', $today)->sum('total_amount');
        $monthRevenue = (clone $completed)->where('created_at', '>=', $month)->sum('total_amount');
        $todayOrders = (clone $completed)->where('created_at', '>=', $today)->count();
        $monthOrders = (clone $completed)->where('created_at', '>=', $month)->count();

        $lowStock = Product::query()
            ->where('is_active', true)
            ->where('stock_qty', '<=', 5)
            ->orderBy('stock_qty')
            ->limit(6)
            ->get()
            ->map(fn (Product $p) => $p->toApiArray())
            ->values();

        $recentOrders = Order::query()
            ->with('user')
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
            'product_count' => Product::query()->where('is_active', true)->count(),
            'user_count' => User::query()->where('is_active', true)->count(),
            'low_stock_count' => Product::query()->where('is_active', true)->where('stock_qty', '<=', 5)->count(),
            'low_stock' => $lowStock,
            'recent_orders' => $recentOrders,
        ]);
    }
}
