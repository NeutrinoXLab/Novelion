<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->string('type')->default('withdrawal')->after('user_id');
            $table->string('reason')->nullable()->change();
            $table->string('refund_method')->nullable()->after('stripe_refund_id');
            $table->string('refund_status')->nullable()->after('refund_method');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_status');
            $table->string('refund_currency', 3)->default('RON')->after('refund_amount');
            $table->string('bank_iban')->nullable()->after('refund_currency');
            $table->timestamp('bank_transfer_accepted_at')->nullable()->after('bank_iban');
            $table->timestamp('confirmation_sent_at')->nullable()->after('requested_at');
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('user_id')->change();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_refund_amount', 10, 2);
            $table->timestamps();
            $table->unique(['return_request_id', 'order_item_id']);
        });

        Schema::create('return_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_photos');
        Schema::dropIfExists('return_items');
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'refund_method', 'refund_status', 'refund_amount',
                'refund_currency', 'bank_iban', 'bank_transfer_accepted_at',
                'confirmation_sent_at',
            ]);
        });
    }
};
