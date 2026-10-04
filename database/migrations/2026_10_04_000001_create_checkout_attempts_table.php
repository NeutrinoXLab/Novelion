<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_hash', 64);
            $table->string('cart_hash', 64);
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('retired_at')->nullable();
            $table->json('stripe_parameters')->nullable();
            $table->timestamp('stripe_started_at')->nullable();
            $table->text('stripe_session_url')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'session_hash', 'retired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
