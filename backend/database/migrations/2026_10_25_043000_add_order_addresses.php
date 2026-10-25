<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_address')) {
                $table->text('customer_address')->nullable()->after('customer_phone');
            }
            if (! Schema::hasColumn('orders', 'shipper_address')) {
                $table->text('shipper_address')->nullable()->after('customer_address');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'customer_address')) {
                $table->dropColumn('customer_address');
            }
            if (Schema::hasColumn('orders', 'shipper_address')) {
                $table->dropColumn('shipper_address');
            }
        });
    }
};
