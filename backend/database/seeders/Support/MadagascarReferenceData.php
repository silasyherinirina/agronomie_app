<?php

namespace Database\Seeders\Support;

/**
 * Données de référence partagées par les Seeders "dataset Madagascar" (mémoire de fin
 * d'étude — voir docs/DATASET-MADAGASCAR.md et docs/SOURCES-AGRICULTURE-MADAGASCAR.md).
 *
 * Cette classe distingue explicitement, pour chaque tableau, ce qui est :
 *  - une donnée publique/officielle (régions, districts, variétés homologuées FOFIFA/SOC,
 *    rendements de référence INSTAT/CGIAR) — jamais modifiée par les Seeders, uniquement
 *    citée ;
 *  - une donnée synthétique de démonstration (noms de coopératives, d'agriculteurs, de
 *    parcelles) — toujours construite de façon à ne jamais se faire passer pour une donnée
 *    officielle (cahier des charges Phase 3/29).
 *
 * Aucune valeur ici n'est utilisée pour "prouver" un fait agronomique : elle sert uniquement
 * à calibrer des ordres de grandeur plausibles pour des données de démonstration.
 */
final class MadagascarReferenceData
{
    /**
     * Les 23 régions officielles de Madagascar (découpage administratif en vigueur depuis la
     * scission de Vatovavy-Fitovinany en 2021 — Vatovavy et Fitovinany distinctes).
     *
     * Source : subdivision administrative publique de Madagascar (23 régions, 119 districts),
     * cf. docs/SOURCES-AGRICULTURE-MADAGASCAR.md.
     */
    public const REGIONS = [
        'Analamanga', 'Vakinankaratra', 'Itasy', 'Bongolava',
        'Haute Matsiatra', "Amoron'i Mania", 'Vatovavy', 'Fitovinany',
        'Atsimo-Atsinanana', 'Ihorombe', 'Menabe', 'Melaky',
        'Atsimo-Andrefana', 'Androy', 'Anosy', 'Analanjirofo',
        'Alaotra-Mangoro', 'Atsinanana', 'Boeny', 'Sofia',
        'Betsiboka', 'Diana', 'Sava',
    ];

    /**
     * Une commune/district réel et un centroïde approximatif (WGS 84) par région — utilisés
     * uniquement pour situer une coopérative/parcelle SYNTHÉTIQUE dans une zone géographique
     * réelle et cohérente (jamais présentés comme des limites cadastrales officielles).
     *
     * Coordonnées : positions approximatives de chefs-lieux publiquement connues (villes de
     * Madagascar), arrondies — pas une mesure GPS de terrain.
     *
     * @var array<string, array{district: string, commune: string, lat: float, lng: float}>
     */
    public const REGION_CENTERS = [
        'Analamanga' => ['district' => 'Antananarivo-Avaradrano', 'commune' => 'Antananarivo', 'lat' => -18.8792, 'lng' => 47.5079],
        'Vakinankaratra' => ['district' => 'Antsirabe I', 'commune' => 'Antsirabe', 'lat' => -19.8667, 'lng' => 47.0333],
        'Itasy' => ['district' => 'Miarinarivo', 'commune' => 'Miarinarivo', 'lat' => -19.0167, 'lng' => 46.7667],
        'Bongolava' => ['district' => 'Tsiroanomandidy', 'commune' => 'Tsiroanomandidy', 'lat' => -18.7667, 'lng' => 46.0500],
        'Haute Matsiatra' => ['district' => 'Fianarantsoa I', 'commune' => 'Fianarantsoa', 'lat' => -21.4536, 'lng' => 47.0854],
        "Amoron'i Mania" => ['district' => 'Ambositra', 'commune' => 'Ambositra', 'lat' => -20.5300, 'lng' => 47.2410],
        'Vatovavy' => ['district' => 'Manakara', 'commune' => 'Manakara', 'lat' => -22.1333, 'lng' => 48.0167],
        'Fitovinany' => ['district' => 'Manakara', 'commune' => 'Vohipeno', 'lat' => -22.3833, 'lng' => 47.8500],
        'Atsimo-Atsinanana' => ['district' => 'Farafangana', 'commune' => 'Farafangana', 'lat' => -22.8167, 'lng' => 47.8333],
        'Ihorombe' => ['district' => 'Ihosy', 'commune' => 'Ihosy', 'lat' => -22.4000, 'lng' => 46.1167],
        'Menabe' => ['district' => 'Morondava', 'commune' => 'Morondava', 'lat' => -20.2833, 'lng' => 44.2833],
        'Melaky' => ['district' => 'Maintirano', 'commune' => 'Maintirano', 'lat' => -18.0667, 'lng' => 44.0333],
        'Atsimo-Andrefana' => ['district' => 'Toliara I', 'commune' => 'Toliara', 'lat' => -23.3500, 'lng' => 43.6667],
        'Androy' => ['district' => 'Ambovombe', 'commune' => 'Ambovombe', 'lat' => -25.1667, 'lng' => 46.0833],
        'Anosy' => ['district' => 'Tolagnaro', 'commune' => 'Tolagnaro', 'lat' => -25.0333, 'lng' => 46.9833],
        'Analanjirofo' => ['district' => 'Fenoarivo Atsinanana', 'commune' => 'Fenoarivo Atsinanana', 'lat' => -17.3833, 'lng' => 49.4167],
        'Alaotra-Mangoro' => ['district' => 'Ambatondrazaka', 'commune' => 'Ambatondrazaka', 'lat' => -17.8333, 'lng' => 48.4167],
        'Atsinanana' => ['district' => 'Toamasina I', 'commune' => 'Toamasina', 'lat' => -18.1492, 'lng' => 49.4023],
        'Boeny' => ['district' => 'Mahajanga I', 'commune' => 'Mahajanga', 'lat' => -15.7167, 'lng' => 46.3167],
        'Sofia' => ['district' => 'Antsohihy', 'commune' => 'Antsohihy', 'lat' => -14.8833, 'lng' => 47.9833],
        'Betsiboka' => ['district' => 'Maevatanana', 'commune' => 'Maevatanana', 'lat' => -16.9500, 'lng' => 46.8333],
        'Diana' => ['district' => 'Antsiranana I', 'commune' => 'Antsiranana', 'lat' => -12.2787, 'lng' => 49.2917],
        'Sava' => ['district' => 'Sambava', 'commune' => 'Sambava', 'lat' => -14.2667, 'lng' => 50.1667],
    ];

    /**
     * Variétés de riz effectivement documentées par la recherche agricole malgache (FOFIFA).
     * Chaque entrée précise la source consultée le 2026-09-06 — voir
     * docs/SOURCES-AGRICULTURE-MADAGASCAR.md pour le détail complet.
     *
     * @var list<array{name: string, note: string, source: string}>
     */
    public const DOCUMENTED_RICE_VARIETIES = [
        [
            'name' => 'Makalioka',
            'note' => "Variété traditionnelle de qualité renommée d'Alaotra-Mangoro (Ambatondrazaka).",
            'source' => 'FOFIFA — https://www.fofifa.mg/les-varietes-de-riz-qui-font-la-renommee-dalaotra-mangoro-dista-tsemaka-makalioka/',
        ],
        [
            'name' => 'Tsemaka',
            'note' => "Variété traditionnelle de qualité renommée d'Alaotra-Mangoro (Ambatondrazaka).",
            'source' => 'FOFIFA — https://www.fofifa.mg/les-varietes-de-riz-qui-font-la-renommee-dalaotra-mangoro-dista-tsemaka-makalioka/',
        ],
        [
            'name' => 'Dista',
            'note' => "Variété traditionnelle de qualité renommée d'Alaotra-Mangoro (Ambatondrazaka).",
            'source' => 'FOFIFA — https://www.fofifa.mg/les-varietes-de-riz-qui-font-la-renommee-dalaotra-mangoro-dista-tsemaka-makalioka/',
        ],
        [
            'name' => 'X265',
            'note' => 'Variété la plus cultivée à Madagascar (référence des essais variétaux FOFIFA).',
            'source' => 'FOFIFA — https://www.fofifa.mg/besalohy-une-nouvelle-victoire-de-la-recherche-agricole-malgache/',
        ],
        [
            'name' => 'Besalohy (FOFIFA X265-MP3)',
            'note' => "Issue du croisement X265 × Koshihikari (gène MP3, tallage accru) ; homologuée après essais DHS. "
                ."Lancement officiel FOFIFA/JIRCAS (programme SATREPS-FyVary, JICA) le 1er septembre 2026.",
            'source' => 'FOFIFA — https://www.fofifa.mg/besalohy-une-nouvelle-victoire-de-la-recherche-agricole-malgache/',
        ],
        [
            'name' => 'Tsipala',
            'note' => 'Variété documentée dans les archives variétales FOFIFA (campagne 1992/93).',
            'source' => 'FOFIFA (mentions dans la documentation variétale historique, cf. docs/SOURCES-AGRICULTURE-MADAGASCAR.md)',
        ],
    ];

    /**
     * Rendements de référence du riz par système de culture, Madagascar — utilisés uniquement
     * pour calibrer l'ORDRE DE GRANDEUR des récoltes synthétiques (jamais recopiés tels quels
     * comme une statistique officielle d'une parcelle précise).
     *
     * Source : Andrianarisoa et al., "Niveau, évolution et facteurs déterminants des rendements
     * du riz à Madagascar", CGIAR/Cornell (cgspace.cgiar.org), consulté le 2026-09-06.
     *
     * Valeurs en tonnes/ha.
     */
    public const RICE_YIELD_REFERENCE_T_HA = [
        'irrigue_grande_saison' => 1.7,
        'irrigue_contre_saison' => 1.2,
        'pluvial_tanety' => 0.9,
        'tavy' => 0.7,
    ];

    /**
     * Ordres de grandeur agronomiques GÉNÉRAUX (FAO, littérature agronomique tropicale) pour
     * les cultures autres que le riz — nettement moins précisément sourcés que le riz
     * ci-dessus : à traiter comme des DONNÉES DÉRIVÉES/PLAUSIBLES, pas des statistiques
     * officielles par culture pour Madagascar. Valeurs en tonnes/ha, exploitation paysanne.
     *
     * @var array<string, float>
     */
    public const GENERIC_CROP_YIELD_REFERENCE_T_HA = [
        'Maïs' => 1.5,
        'Manioc' => 9.0,
        'Patate douce' => 7.0,
        'Pomme de terre' => 10.0,
        'Haricot' => 0.8,
        'Arachide' => 1.0,
        'Sorgho' => 1.0,
        'Niébé' => 0.6,
        'Pois du Cap' => 0.9,
        'Voandzou' => 0.7,
        'Soja' => 1.2,
        'Sésame' => 0.5,
        'Blé' => 1.3,
        'Triticale' => 1.4,
        'Taro' => 8.0,
        'Café' => 0.4,
        'Vanille' => 0.3,
        'Coton' => 0.9,
        'Gingembre' => 6.0,
    ];

    /** Prénoms malgaches courants — composés librement pour des identités DE DÉMONSTRATION,
     * jamais reliés à une personne réelle recensée (cahier des charges Phase 6). */
    public const FARMER_FIRST_NAMES = [
        'Rakoto', 'Rasoa', 'Andry', 'Voahangy', 'Njara', 'Tahina', 'Hery', 'Fara',
        'Solo', 'Miora', 'Zo', 'Fanja', 'Tovo', 'Nirina', 'Lala', 'Baholy',
        'Tiana', 'Faniry', 'Haja', 'Mamy', 'Ravo', 'Sitraka', 'Tokiniaina', 'Zafy',
    ];

    /** Noms de famille malgaches courants — même principe que ci-dessus. */
    public const FARMER_LAST_NAMES = [
        'Rakotondrabe', 'Randrianasolo', 'Rabemananjara', 'Rasoanaivo', 'Andriamahefa',
        'Ravaoarimalala', 'Rakotoarison', 'Razanajatovo', 'Rasolofomanana', 'Andriantsitohaina',
        'Ratsimbazafy', 'Raharinaivo', 'Rakotozafy', 'Razafindrakoto', 'Ramanantsoa',
        'Andrianarisoa', 'Rabarijaona', 'Rasendrason', 'Ratovoson', 'Razakarivony',
    ];
}
