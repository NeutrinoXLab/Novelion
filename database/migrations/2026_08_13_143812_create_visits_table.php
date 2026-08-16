<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rulează migrarea.
     */
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {

            $table->id();

            // ID-ul sesiunii vizitatorului.
            // Ne ajută să nu numărăm fiecare refresh ca vizită nouă.
            $table->string('session_id')->index();

            // Adresa IP a vizitatorului.
            $table->string('ip_address')->nullable();

            // Pagina vizitată.
            $table->text('url')->nullable();

            // Pagina de unde a venit vizitatorul.
            $table->text('referrer')->nullable();

            // Informații despre browser/dispozitiv.
            $table->text('user_agent')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Anulează migrarea.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};