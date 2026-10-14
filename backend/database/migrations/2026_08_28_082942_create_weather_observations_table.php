<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table placeholder pour le Module L (Météo — extension non bloquante, Phase 11).
     * Structure minimale prévue par la Phase 0 pour éviter un remodelage ultérieur du schéma.
     */
    public function up(): void
    {
        Schema::create('weather_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();
            $table->date('observed_date');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('precipitation', 6, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['plot_id', 'observed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_observations');
    }
};
