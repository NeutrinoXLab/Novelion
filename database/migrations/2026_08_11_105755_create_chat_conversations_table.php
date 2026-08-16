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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            // Client autentificat, dacă există
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Pentru vizitatorii care nu sunt autentificați
            $table->string('session_id')->nullable()->index();

            // Numele afișat al conversației
            $table->string('name')->nullable();

            // Email-ul clientului, dacă îl vom solicita
            $table->string('email')->nullable();

            // Statusul conversației
            $table->enum('status', [
                'open',
                'closed',
            ])->default('open');

            // Ultima activitate din conversație
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};