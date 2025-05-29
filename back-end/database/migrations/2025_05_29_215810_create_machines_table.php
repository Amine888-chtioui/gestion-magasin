<?php
// database/migrations/xxxx_xx_xx_create_machines_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('numero_serie', 50)->unique();
            $table->string('modele', 50)->default('TELSOSPLICE TS3');
            $table->text('description')->nullable();
            $table->string('localisation', 100)->nullable();
            $table->enum('statut', ['actif', 'inactif', 'maintenance'])->default('actif');
            $table->date('date_installation')->nullable();
            $table->date('derniere_maintenance')->nullable();
            $table->json('specifications_techniques')->nullable(); // Stockage flexible des specs
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};