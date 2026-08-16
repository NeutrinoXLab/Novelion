<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Relații
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            // Identificare
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('ean')->nullable();
            $table->string('supplier_reference')->nullable();

            // Prețuri
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('vat_rate')->default(19);

            // Stoc
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->decimal('weight', 8, 2)->nullable();

            // Descriere
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // Imagine principală
            $table->string('main_image_path')->nullable();

            // Vizibilitate
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(true);
            $table->boolean('is_on_sale')->default(false);

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};