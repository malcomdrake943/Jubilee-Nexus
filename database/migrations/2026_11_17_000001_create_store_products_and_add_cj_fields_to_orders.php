<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->string('cj_pid')->nullable()->unique();
            $table->string('cj_vid')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category_name')->nullable()->index();
            $table->string('cj_category_id')->nullable();
            $table->longText('description')->nullable();
            $table->text('image_url')->nullable();
            $table->json('gallery_images')->nullable();
            $table->json('variants')->nullable();
            $table->decimal('cj_cost_price', 10, 2)->default(0);
            $table->decimal('retail_price', 10, 2);
            $table->unsignedInteger('weight_grams')->default(500);
            $table->string('source_country_code', 5)->default('CN');
            $table->integer('stock')->default(100);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('store_product_id')->nullable()->after('platform_id')->constrained('store_products')->nullOnDelete();
            $table->string('cj_pid')->nullable()->after('store_product_id');
            $table->string('cj_vid')->nullable()->after('cj_pid');
            $table->string('cj_order_id')->nullable()->index()->after('cj_vid');
            $table->string('cj_order_status')->nullable()->after('cj_order_id');
            $table->string('cj_logistic_name')->nullable()->after('cj_order_status');
            $table->decimal('cj_shipping_cost', 10, 2)->nullable()->after('cj_logistic_name');
            $table->text('cj_error_message')->nullable()->after('cj_shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['store_product_id']);
            $table->dropIndex(['cj_order_id']);
            $table->dropColumn([
                'store_product_id',
                'cj_pid',
                'cj_vid',
                'cj_order_id',
                'cj_order_status',
                'cj_logistic_name',
                'cj_shipping_cost',
                'cj_error_message',
            ]);
        });

        Schema::dropIfExists('store_products');
    }
};
