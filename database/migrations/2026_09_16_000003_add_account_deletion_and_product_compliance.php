<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('account_deleted_at')->nullable()->index();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('manufacturer_name')->nullable();
            $table->text('manufacturer_contact')->nullable();
            $table->string('model_identifier')->nullable();
            $table->string('eu_responsible_person_name')->nullable();
            $table->text('eu_responsible_person_contact')->nullable();
            $table->text('warnings')->nullable();
            $table->longText('safety_instructions')->nullable();
            $table->text('commercial_warranty')->nullable();
            $table->boolean('requires_eu_responsible_person')->default(false);
        });

        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamp('effective_at');
            $table->timestamps();
            $table->index(['product_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'manufacturer_name', 'manufacturer_contact', 'model_identifier',
                'eu_responsible_person_name', 'eu_responsible_person_contact',
                'warnings', 'safety_instructions', 'commercial_warranty',
                'requires_eu_responsible_person',
            ]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('account_deleted_at'));
    }
};
