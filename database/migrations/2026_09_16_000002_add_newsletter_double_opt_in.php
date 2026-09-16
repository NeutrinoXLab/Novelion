<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->unique()->after('email');
            $table->timestamp('confirmation_sent_at')->nullable()->after('subscribed_at');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_sent_at');
            $table->string('consent_ip', 45)->nullable()->after('confirmed_at');
            $table->text('consent_user_agent')->nullable()->after('consent_ip');
        });
    }

    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropUnique(['confirmation_token']);
            $table->dropColumn(['confirmation_token', 'confirmation_sent_at', 'confirmed_at', 'consent_ip', 'consent_user_agent']);
        });
    }
};
