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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('length', 8, 2)
                ->nullable()
                ->after('weight');

            $table->decimal('width', 8, 2)
                ->nullable()
                ->after('length');

            $table->decimal('height', 8, 2)
                ->nullable()
                ->after('width');
        });
    }

    /**
     * Anulează migrarea.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'length',
                'width',
                'height',
            ]);
        });
    }
};