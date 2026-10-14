<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_versions', function (Blueprint $table) {
            // Identifiant du modèle côté service IA (dossier models/<id>/ de ai-service,
            // voir ai-service/app/schemas/model.py) — permet à Laravel de retrouver/upserter
            // le ModelVersion correspondant à une réponse de FastAPI (Phase 10, intégration IA).
            $table->string('external_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
