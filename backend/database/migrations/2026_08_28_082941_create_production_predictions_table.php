<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('crop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('agricultural_campaigns')->nullOnDelete();
            $table->decimal('predicted_yield', 12, 3);
            // Variables d'entrée envoyées au service ML (traçabilité de la prédiction)
            $table->jsonb('input_features')->nullable();
            $table->timestamp('predicted_at')->nullable();
            $table->timestamps();

            $table->index(['plot_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_predictions');
    }
};
