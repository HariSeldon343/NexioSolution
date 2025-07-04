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
        Schema::create('evento_partecipanti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->foreignId('utente_id')->constrained('users')->onDelete('cascade');
            $table->enum('stato', ['invitato', 'confermato', 'rifiutato', 'forse'])->default('invitato');
            $table->timestamp('confermato_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            
            // Indici
            $table->unique(['evento_id', 'utente_id']);
            $table->index('stato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evento_partecipanti');
    }
}; 