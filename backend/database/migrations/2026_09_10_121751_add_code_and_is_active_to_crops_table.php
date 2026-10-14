<?php

use App\Models\Crop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Architecture multi-cultures : identifiant stable de la culture pour le service IA
     * (`crop_key`), persisté au lieu d'être recalculé à la volée par un slug du nom (fragile —
     * un renommage de culture aurait silencieusement cassé le lien vers ses modèles déjà
     * entraînés). `is_active` masque proprement les cultures de test/intégration héritées
     * (créées par des exécutions passées de tests directement sur la base de développement,
     * jamais par les Seeders officiels) sans les supprimer — elles restent disponibles pour les
     * tests automatisés qui en dépendent (cahier des charges §8).
     *
     * Les 20 cultures réellement documentées (voir docs/SOURCES-AGRICULTURE-MADAGASCAR.md et
     * CropSeeder) sont explicitement listées ci-dessous pour le backfill : c'est la seule
     * source de vérité utilisée pour décider quelles lignes existantes sont réelles, plutôt
     * qu'une heuristique fragile sur le nom (ex. contient "(test)").
     */
    private const REAL_CROP_NAMES = [
        'Riz', 'Maïs', 'Blé', 'Triticale', 'Sorgho',
        'Manioc', 'Patate douce', 'Pomme de terre', 'Taro', 'Gingembre',
        'Haricot', 'Arachide', 'Niébé', 'Pois du Cap', 'Voandzou', 'Soja',
        'Sésame', 'Café', 'Vanille', 'Coton',
    ];

    public function up(): void
    {
        Schema::table('crops', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->boolean('is_active')->default(true)->after('cycle_duration_days');
        });

        Crop::query()->whereIn('name', self::REAL_CROP_NAMES)->get()->each(function (Crop $crop) {
            $crop->forceFill([
                'code' => Str::slug($crop->name, '_'),
                'is_active' => true,
            ])->saveQuietly();
        });

        // Toute culture PAS dans la liste ci-dessus (résidus de tests exécutés directement sur
        // la base de développement, ex. "cupiditate (test)", "Riz Integration") : désactivée,
        // jamais supprimée — reste utilisable par les tests automatisés qui la référencent.
        Crop::query()->whereNotIn('name', self::REAL_CROP_NAMES)->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('crops', function (Blueprint $table) {
            $table->dropColumn(['code', 'is_active']);
        });
    }
};
