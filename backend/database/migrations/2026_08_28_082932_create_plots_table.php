<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Géométrie obligatoire (objectif Phase 0 : géolocalisation des parcelles). SRID 4326.
            // Architecture ouverte à MultiPolygon plus tard (colonne dédiée si besoin, pas de migration destructive).
            $table->geometry('geom', subtype: 'polygon', srid: 4326);
            // Superficie recalculée via ST_Area(geography(geom))/10000, jamais saisie manuellement.
            $table->decimal('area_ha', 10, 4)->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->spatialIndex('geom');
            $table->index(['cooperative_id', 'farmer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plots');
    }
};
