<?php
// database/migrations/xxxx_xx_xx_create_composants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composants', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('reference', 50)->unique();
            $table->foreignId('machine_id')->constrained()->onDelete('cascade');
            $table->foreignId('type_id')->constrained()->onDelete('restrict');
            $table->text('description')->nullable();
            $table->enum('statut', ['bon', 'usure', 'defaillant', 'remplace'])->default('bon');
            $table->integer('quantite')->default(1);
            $table->decimal('prix_unitaire', 10, 2)->nullable();
            $table->string('fournisseur', 100)->nullable();
            $table->date('date_installation')->nullable();
            $table->date('derniere_inspection')->nullable();
            $table->date('prochaine_inspection')->nullable();
            $table->integer('duree_vie_estimee')->nullable(); // en mois
            $table->text('notes')->nullable();
            $table->json('caracteristiques')->nullable(); // Stockage flexible des caractéristiques
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('composants');
    }
};