<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rulează migrarea.
     */
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();

            // Denumirea categoriei de transport: S, M, L, XL etc.
            $table->string('name');

            // Greutatea maximă acceptată pentru această categorie.
            $table->decimal('max_weight', 8, 2)->nullable();

            // Volumul maxim acceptat în cm³.
            $table->decimal('max_volume', 12, 2)->nullable();

            // Prețul transportului.
            $table->decimal('price', 10, 2);

            // Ordinea în care sunt verificate regulile.
            $table->unsignedInteger('sort_order')->default(0);

            // Permite dezactivarea unei reguli fără să o ștergem.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Anulează migrarea.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};