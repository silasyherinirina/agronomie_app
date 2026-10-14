<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module M — Ventes agricoles. Une vente représente la commercialisation réelle d'une
     * production ; elle doit rester consultable même si l'élément qu'elle référence disparaît
     * ensuite (traçabilité financière), d'où softDeletes() et des FK jamais en cascadeOnDelete().
     *
     * Choix de conception (voir rapport d'audit Phase A) : `crop_cycle_id`, `crop_id`,
     * `variety_id`, `plot_id`, `farmer_id`, `campaign_id` sont TOUS nullables et stockés
     * directement plutôt que dérivés uniquement de `crop_cycle_id` — même précédent déjà posé
     * par `plots` (qui stocke `cooperative_id` ET `farmer_id` bien que dérivable via `farmers`)
     * et par `production_predictions`. Raison métier réelle : une coopérative doit pouvoir
     * enregistrer une vente agrégée (plusieurs récoltes, ou production non suivie précisément
     * par cycle) tout en la rattachant à une culture/variété/parcelle pour les statistiques —
     * imposer `crop_cycle_id` non nullable interdirait ce cas réel.
     *
     * `cooperative_id` reste seul obligatoire : c'est le point d'ancrage de la sécurité par rôle
     * (cooperative_manager) et il ne doit jamais pouvoir être nul, contrairement aux autres FK.
     * `restrictOnDelete()` dessus (comme farmers/plots) : on ne supprime jamais une coopérative
     * ayant des ventes enregistrées.
     *
     * `total_amount` n'est PAS une colonne générée SQL : SaleService le recalcule toujours
     * lui-même (quantity × unit_price) avant écriture — jamais une valeur envoyée par le
     * frontend qui serait simplement rejouée telle quelle (cahier des charges §6).
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained('crop_varieties')->nullOnDelete();
            $table->foreignId('crop_cycle_id')->nullable()->constrained('crop_cycles')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('agricultural_campaigns')->nullOnDelete();

            $table->string('buyer_name')->nullable();
            $table->string('buyer_contact')->nullable();
            $table->date('sale_date');

            // Même précision que harvests.quantity (12,3) : une vente représente tout ou partie
            // d'une récolte, les deux grandeurs doivent rester comparables sans perte.
            $table->decimal('quantity', 12, 3);
            // Unité texte libre, volontairement non normalisée (kg, tonne, sac...) — comme
            // harvests.unit et inputs.unit : jamais additionnée entre unités différentes, voir
            // ReportDataBuilder/DashboardService (regroupement par unité, pas de conversion
            // inventée).
            $table->string('unit');

            $table->decimal('unit_price', 12, 2);
            // Précision supérieure à unit_price : quantity peut être grande (tonnes) et le
            // produit quantity × unit_price doit rester exact sans dépassement/arrondi prématuré.
            $table->decimal('total_amount', 14, 2);
            $table->string('currency', 3)->default('MGA');

            $table->text('observations')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cooperative_id', 'sale_date']);
            $table->index('farmer_id');
            $table->index('plot_id');
            $table->index('crop_id');
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
