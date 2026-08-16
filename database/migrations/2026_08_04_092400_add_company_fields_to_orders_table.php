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

            $table->enum('customer_type', [
                'individual',
                'company',
            ])->default('individual')->after('user_id');

            $table->string('company_name')->nullable()->after('postal_code');

            $table->string('company_vat')->nullable()->after('company_name');

            $table->string('company_registration')->nullable()->after('company_vat');

            $table->string('company_address')->nullable()->after('company_registration');

            $table->string('company_city')->nullable()->after('company_address');

            $table->string('company_county')->nullable()->after('company_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn([
                'customer_type',
                'company_name',
                'company_vat',
                'company_registration',
                'company_address',
                'company_city',
                'company_county',
            ]);
        });
    }
};