<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            // Pas de cascade : une récolte enregistrée doit rester traçable même si le cycle
            // cultural est clôturé (suppression restreinte, Phase 0 §N).
            $table->foreignId('crop_cycle_id')->constrained()->restrictOnDelete();
            $table->date('harvest_date');
            $table->decimal('quantity', 12, 3);
            $table->string('unit');
            // Stratégie de rendement explicite (Phase 0 §F) : 'calculated' = quantity / plot.area_ha,
            // 'declared' = valeur saisie directement par l'utilisateur.
            $table->decimal('yield_value', 12, 3)->nullable();
            $table->string('yield_source')->default('calculated');
            $table->string('quality')->nullable();
            $table->text('observations')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('crop_cycle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
