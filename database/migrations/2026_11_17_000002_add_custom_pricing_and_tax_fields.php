<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->decimal('custom_shipping_price', 10, 2)->nullable()->after('retail_price');
            $table->decimal('tax_percent', 5, 2)->nullable()->after('custom_shipping_price');
            $table->decimal('custom_tax_amount', 10, 2)->nullable()->after('tax_percent');
        });

        Schema::table('delivery_options', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('duration');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_amount', 10, 2)->default(0)->after('cj_shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });

        Schema::table('delivery_options', function (Blueprint $table) {
            $table->dropColumn('price');
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->dropColumn(['custom_shipping_price', 'tax_percent', 'custom_tax_amount']);
        });
    }
};
