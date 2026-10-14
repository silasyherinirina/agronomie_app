<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Architecture multi-cultures (voir ai-service/app/ml/crop_registry.py) : un modèle entraîné
     * concerne désormais UNE culture précise, pas implicitement le riz. `crop_id` est nullable
     * (compatible avec les modèles déjà en base avant cette extension, dont la culture n'était
     * pas trackée) et réutilise la relation existante vers `crops` plutôt que d'en recréer une
     * (cahier des charges §37 : "réutiliser les relations existantes"). `nullOnDelete` — un
     * modèle entraîné reste consultable même si sa culture est supprimée du catalogue.
     */
    public function up(): void
    {
        Schema::table('model_versions', function (Blueprint $table) {
            $table->foreignId('crop_id')->nullable()->after('id')->constrained('crops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crop_id');
        });
    }
};
