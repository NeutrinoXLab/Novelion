<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactional_emails', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained('returns')->restrictOnDelete();
            $table->string('kind');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_type')->nullable();
            $table->timestamps();
            $table->index(['sent_at', 'failed_at', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_emails');
    }
};
