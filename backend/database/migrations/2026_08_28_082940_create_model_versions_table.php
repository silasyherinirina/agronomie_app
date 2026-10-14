<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // linear_regression | random_forest | gradient_boosting (Phase 9)
            $table->string('algorithm');
            $table->string('version');
            $table->timestamp('trained_at')->nullable();
            // MAE, RMSE, R², hyperparamètres, taille du dataset, etc. (documenté en Phase 9)
            $table->jsonb('metrics')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
