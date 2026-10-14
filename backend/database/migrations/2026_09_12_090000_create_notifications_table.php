<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Centre de notifications — schéma standard Laravel *Database Notifications*
     * (`Illuminate\Notifications\Notifiable`, déjà utilisé sur `User` pour
     * `VerifyEmail`/`ResetPassword`). Aucune architecture parallèle : on réutilise le système
     * natif plutôt que d'en recréer un, `notifiable_type`/`notifiable_id` permettant de garder
     * la porte ouverte à d'autres `notifiable` que `User` sans migration supplémentaire.
     *
     * `data` reste `text` (et non `json`) pour rester strictement compatible avec
     * `Illuminate\Notifications\DatabaseNotification`, qui sérialise/désérialise lui-même ce
     * champ — un type `json` natif PostgreSQL n'apporterait rien ici et casserait l'hypothèse
     * du framework sur la colonne.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
