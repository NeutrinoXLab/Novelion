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
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('subject');
            $table->string('title');
            $table->longText('content');

            $table->enum('status', [
                'draft',
                'sending',
                'sent',
                'failed',
            ])->default('draft');

            $table->unsignedInteger('recipients_count')->default(0);

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
