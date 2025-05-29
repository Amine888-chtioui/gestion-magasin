<?php
// database/migrations/xxxx_xx_xx_create_demandes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_demande', 20)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->constrained()->onDelete('cascade');
            $table->foreignId('composant_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type_demande', ['maintenance', 'piece', 'reparation', 'inspection']);
            $table->enum('priorite', ['basse', 'normale', 'haute', 'critique'])->default('normale');
            $table->enum('statut', ['en_attente', 'en_cours', 'acceptee', 'refusee', 'terminee'])->default('en_attente');
            $table->string('titre', 150);
            $table->text('description');
            $table->text('justification')->nullable();
            $table->integer('quantite_demandee')->nullable();
            $table->decimal('budget_estime', 10, 2)->nullable();
            $table->date('date_souhaite')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->onDelete('set null');
            $table->text('commentaire_admin')->nullable();
            $table->timestamp('date_traitement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};