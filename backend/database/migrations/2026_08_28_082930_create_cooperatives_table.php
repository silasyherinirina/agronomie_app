<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('region');
            $table->string('district')->nullable();
            $table->string('commune')->nullable();
            // Localisation ponctuelle (siège de la coopérative). SRID 4326 (WGS 84).
            $table->geometry('location', subtype: 'point', srid: 4326)->nullable();
            $table->foreignId('manager_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->spatialIndex('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperatives');
    }
};
