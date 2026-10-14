<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_id')->constrained()->restrictOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained('crop_varieties')->nullOnDelete();
            $table->foreignId('campaign_id')->constrained('agricultural_campaigns')->restrictOnDelete();
            $table->date('planting_date')->nullable();
            $table->date('expected_harvest_date')->nullable();
            $table->string('status')->default('in_progress');
            $table->timestamps();

            $table->index(['plot_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};
