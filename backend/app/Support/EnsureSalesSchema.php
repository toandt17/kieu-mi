<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureSalesSchema
{
    public static function run(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        if (! Schema::hasColumn('order_items', 'returned_qty')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedInteger('returned_qty')->default(0)->after('quantity');
            });
        }

        if (! Schema::hasTable('order_returns')) {
            Schema::create('order_returns', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->string('reason', 120);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('total_amount')->default(0);
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_return_items')) {
            Schema::create('order_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_return_id')->constrained('order_returns')->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name');
                $table->unsignedInteger('quantity');
                $table->unsignedBigInteger('sale_price')->default(0);
                $table->unsignedBigInteger('line_total')->default(0);
                $table->timestamps();
            });
        }
    }
}
