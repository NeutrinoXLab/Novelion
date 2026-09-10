<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->string('stripe_refund_id')
                ->nullable()
                ->after('status');

            $table->timestamp('refunded_at')
                ->nullable()
                ->after('received_at');

            $table->timestamp('stock_restored_at')
                ->nullable()
                ->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_refund_id',
                'refunded_at',
                'stock_restored_at',
            ]);
        });
    }
};