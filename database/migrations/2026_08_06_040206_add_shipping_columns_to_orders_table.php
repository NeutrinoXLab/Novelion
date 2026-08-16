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
        Schema::table('orders', function (Blueprint $table) {

            $table->string('courier')->nullable()->after('payment_status');

            $table->string('awb_number')->nullable()->after('courier');

            $table->string('tracking_url')->nullable()->after('awb_number');

            $table->timestamp('shipped_at')->nullable()->after('tracking_url');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn([
                'courier',
                'awb_number',
                'tracking_url',
                'shipped_at',
            ]);

        });
    }
};