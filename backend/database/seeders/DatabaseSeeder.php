<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockImport;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->create([
            'name' => 'Quản trị Kiều Mi',
            'email' => 'admin@kieumi.vn',
            'phone' => '0901234567',
            'role' => 'admin',
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $staff = User::query()->create([
            'name' => 'Nhân viên bán hàng',
            'email' => 'nhanvien@kieumi.vn',
            'phone' => '0912345678',
            'role' => 'staff',
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $catalog = [
            ['name' => 'Son môi Kiều Mi Velvet', 'sku' => 'SM-001', 'listed_price' => 189000, 'qty' => 40],
            ['name' => 'Kem dưỡng ẩm ban đêm', 'sku' => 'KD-012', 'listed_price' => 259000, 'qty' => 25],
            ['name' => 'Serum vitamin C 15ml', 'sku' => 'SR-015', 'listed_price' => 329000, 'qty' => 18],
            ['name' => 'Mặt nạ giấy collagen', 'sku' => 'MN-008', 'listed_price' => 29000, 'qty' => 80],
            ['name' => 'Sữa rửa mặt dịu nhẹ', 'sku' => 'SRM-003', 'listed_price' => 149000, 'qty' => 30],
            ['name' => 'Nước tẩy trang 300ml', 'sku' => 'TT-300', 'listed_price' => 119000, 'qty' => 22],
        ];

        $products = collect($catalog)->map(function (array $row) use ($admin) {
            $product = Product::query()->create([
                'name' => $row['name'],
                'sku' => $row['sku'],
                'listed_price' => $row['listed_price'],
                'stock_qty' => $row['qty'],
                'imported_qty' => $row['qty'],
                'sold_qty' => 0,
                'is_active' => true,
            ]);

            StockImport::query()->create([
                'product_id' => $product->id,
                'user_id' => $admin->id,
                'quantity' => $row['qty'],
                'unit_cost' => (int) round($row['listed_price'] * 0.55),
                'note' => 'Nhập kho ban đầu',
            ]);

            return $product;
        });

        $lipstick = $products[0];
        $mask = $products[3];
        $serum = $products[2];

        $this->createOrder($staff, 'Nguyễn Thị Hoa', '0988111222', [
            [$lipstick, 2, 175000],
            [$mask, 5, 25000],
        ]);

        $this->createOrder($admin, 'Trần Minh Anh', '0977666555', [
            [$serum, 1, 329000],
            [$lipstick, 1, 189000],
        ]);
    }

    /**
     * @param  list<array{0: Product, 1: int, 2: int}>  $rows
     */
    private function createOrder(User $user, string $customer, string $phone, array $rows): void
    {
        $total = 0;
        foreach ($rows as [$product, $qty, $sale]) {
            $total += $sale * $qty;
        }

        $count = Order::query()->count() + 1;
        $order = Order::query()->create([
            'code' => 'DH'.now()->format('ymd').str_pad((string) $count, 4, '0', STR_PAD_LEFT),
            'customer_name' => $customer,
            'customer_phone' => $phone,
            'status' => 'completed',
            'total_amount' => $total,
            'note' => 'Đơn mẫu',
            'user_id' => $user->id,
        ]);

        foreach ($rows as [$product, $qty, $sale]) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'listed_price' => $product->listed_price,
                'sale_price' => $sale,
                'quantity' => $qty,
                'line_total' => $sale * $qty,
            ]);
            $product->decrement('stock_qty', $qty);
            $product->increment('sold_qty', $qty);
        }
    }
}
