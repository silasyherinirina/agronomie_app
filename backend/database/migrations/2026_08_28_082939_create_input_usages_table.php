<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('input_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('input_id')->constrained()->restrictOnDelete();
            $table->foreignId('plot_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('agricultural_campaigns')->restrictOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('date');
            $table->text('observations')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['plot_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('input_usages');
    }
};
