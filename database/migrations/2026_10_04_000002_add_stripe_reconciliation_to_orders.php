<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('stripe_reconcile_claim')->nullable();
            $table->timestamp('stripe_reconcile_next_at')->nullable();
            $table->timestamp('stripe_reconcile_checked_at')->nullable();
            $table->unsignedInteger('stripe_reconcile_failures')->default(0);
            $table->string('stripe_reconcile_result', 64)->nullable();
            $table->json('stripe_reconcile_cursor')->nullable();
            $table->index(['payment_method', 'stripe_reconcile_next_at', 'id'], 'orders_stripe_reconcile_due');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_stripe_reconcile_due');
            $table->dropColumn(['stripe_reconcile_claim', 'stripe_reconcile_next_at', 'stripe_reconcile_checked_at',
                'stripe_reconcile_failures', 'stripe_reconcile_result', 'stripe_reconcile_cursor']);
        });
    }
};
