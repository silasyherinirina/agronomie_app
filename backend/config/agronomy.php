<?php

/**
 * Module de conseil agronomique prédictif basé sur la météo — configuration centralisée.
 *
 * AVERTISSEMENT (voir cahier des charges §7/20) : cette classification agroécologique et ces
 * profils climatiques par culture sont une PREMIÈRE VERSION d'aide à la décision, construite à
 * partir de connaissances agronomiques générales (FAO et littérature agronomique tropicale
 * courante). Ce ne sont PAS des seuils scientifiques validés spécifiquement pour Madagascar,
 * et une région peut contenir plusieurs microclimats/types de sols que cette classification ne
 * capture pas. Toute réponse de l'API porte la mention correspondante — voir
 * WeatherAgronomicAdviceService::DISCLAIMER.
 *
 * Modifier les seuils ici n'importe jamais de logique applicative : c'est le but de centraliser
 * la configuration (cahier des charges §10).
 */

return [

    /*
     |----------------------------------------------------------------------
     | Zones agroécologiques
     |----------------------------------------------------------------------
     | Chaque zone associe des régions administratives réelles de Madagascar à un climat
     | dominant et une liste de cultures traditionnellement adaptées. Les noms de cultures sont
     | recherchés dans la table `crops` existante (jamais une nouvelle table) : une culture listée
     | ici qui n'existe pas encore en base est simplement ignorée lors du calcul des
     | recommandations — voir AgroEcologicalZoneService::missingCropNames() et
     | docs/CONSEIL-AGRONOMIQUE.md, section "Cultures à ajouter".
     |----------------------------------------------------------------------
     */
    'zones' => [
        'hautes_terres' => [
            'name' => 'Hautes Terres',
            'climate' => 'Frais / tempéré',
            'regions' => ['Analamanga', 'Vakinankaratra', 'Itasy', "Amoron'i Mania", 'Haute Matsiatra', 'Ihorombe'],
            'crops' => ['Riz', 'Pomme de terre', 'Carotte', 'Haricot', 'Pois', 'Maïs'],
        ],
        'est_humide' => [
            'name' => 'Est humide',
            'climate' => 'Chaud et très humide',
            'regions' => ['Atsinanana', 'Analanjirofo', 'Vatovavy', 'Fitovinany', 'Atsimo-Atsinanana'],
            'crops' => ['Riz', 'Vanille', 'Girofle', 'Café', 'Litchi', 'Banane', 'Manioc'],
        ],
        'nord_nord_ouest' => [
            'name' => 'Nord / Nord-Ouest',
            'climate' => 'Tropical humide à subhumide',
            'regions' => ['Diana', 'Sava', 'Sofia'],
            'crops' => ['Riz', 'Vanille', 'Café', 'Maïs', 'Arachide', 'Haricot', 'Oignon'],
        ],
        'ouest_savane' => [
            'name' => 'Ouest / Savane',
            'climate' => 'Tropical / savane, saison sèche marquée',
            'regions' => ['Boeny', 'Melaky', 'Menabe', 'Bongolava'],
            'crops' => ['Riz', 'Maïs', 'Manioc', 'Arachide', 'Haricot', 'Pois du Cap'],
        ],
        'sud_aride' => [
            'name' => 'Sud / Sud-Ouest aride',
            'climate' => 'Semi-aride à aride',
            'regions' => ['Androy', 'Anosy', 'Atsimo-Andrefana'],
            // "Élevage" n'est pas une culture (hors du modèle Crop) — volontairement omis de la
            // liste de correspondance, mais conservé en commentaire pour ne pas perdre
            // l'information du cahier des charges §7.
            'crops' => ['Manioc', 'Patate douce', 'Maïs', 'Niébé', 'Sorgho'],
        ],
    ],

    /*
     |----------------------------------------------------------------------
     | Profils climatiques indicatifs par culture
     |----------------------------------------------------------------------
     | temp_min/temp_max : plage tolérée (score > 0) ; temp_optimal : sous-plage idéale (score
     | plein). Mêmes principes pour l'humidité (%) et la pluviométrie moyenne journalière sur la
     | fenêtre analysée (mm/j) — 'low' < rainfall_low_max, 'moderate' entre les deux,
     | 'high' > rainfall_high_min. `rainfall_preference` indique la catégorie idéale de la
     | culture parmi 'low' | 'moderate' | 'high'.
     |----------------------------------------------------------------------
     */
    'crop_climate_profiles' => [
        'Riz' => ['temp_min' => 18, 'temp_optimal' => [24, 32], 'temp_max' => 37, 'humidity_min' => 55, 'humidity_optimal' => [70, 95], 'humidity_max' => 100, 'rainfall_preference' => 'high'],
        'Pomme de terre' => ['temp_min' => 8, 'temp_optimal' => [14, 20], 'temp_max' => 26, 'humidity_min' => 40, 'humidity_optimal' => [55, 80], 'humidity_max' => 90, 'rainfall_preference' => 'moderate'],
        'Haricot' => ['temp_min' => 12, 'temp_optimal' => [18, 24], 'temp_max' => 30, 'humidity_min' => 40, 'humidity_optimal' => [50, 75], 'humidity_max' => 88, 'rainfall_preference' => 'moderate'],
        'Maïs' => ['temp_min' => 15, 'temp_optimal' => [20, 28], 'temp_max' => 35, 'humidity_min' => 40, 'humidity_optimal' => [50, 85], 'humidity_max' => 95, 'rainfall_preference' => 'moderate'],
        'Manioc' => ['temp_min' => 17, 'temp_optimal' => [24, 32], 'temp_max' => 38, 'humidity_min' => 30, 'humidity_optimal' => [40, 85], 'humidity_max' => 95, 'rainfall_preference' => 'moderate'],
        'Vanille' => ['temp_min' => 18, 'temp_optimal' => [24, 30], 'temp_max' => 34, 'humidity_min' => 60, 'humidity_optimal' => [75, 95], 'humidity_max' => 100, 'rainfall_preference' => 'high'],
        'Café' => ['temp_min' => 14, 'temp_optimal' => [18, 26], 'temp_max' => 30, 'humidity_min' => 50, 'humidity_optimal' => [60, 90], 'humidity_max' => 95, 'rainfall_preference' => 'high'],
        'Arachide' => ['temp_min' => 18, 'temp_optimal' => [24, 30], 'temp_max' => 35, 'humidity_min' => 30, 'humidity_optimal' => [40, 70], 'humidity_max' => 85, 'rainfall_preference' => 'moderate'],
        'Patate douce' => ['temp_min' => 15, 'temp_optimal' => [22, 28], 'temp_max' => 34, 'humidity_min' => 30, 'humidity_optimal' => [40, 75], 'humidity_max' => 90, 'rainfall_preference' => 'moderate'],
        'Niébé' => ['temp_min' => 18, 'temp_optimal' => [25, 32], 'temp_max' => 37, 'humidity_min' => 25, 'humidity_optimal' => [30, 65], 'humidity_max' => 80, 'rainfall_preference' => 'low'],
        'Sorgho' => ['temp_min' => 18, 'temp_optimal' => [25, 32], 'temp_max' => 38, 'humidity_min' => 25, 'humidity_optimal' => [30, 65], 'humidity_max' => 80, 'rainfall_preference' => 'low'],
        'Pois du Cap' => ['temp_min' => 15, 'temp_optimal' => [22, 27], 'temp_max' => 32, 'humidity_min' => 35, 'humidity_optimal' => [40, 70], 'humidity_max' => 85, 'rainfall_preference' => 'moderate'],
    ],

    'rainfall_bands_mm_per_day' => [
        'low_max' => 2.0,
        'high_min' => 8.0,
    ],

    /*
     |----------------------------------------------------------------------
     | Pondération du score d'adaptation (total = 100)
     |----------------------------------------------------------------------
     */
    'scoring' => [
        'zone_match_points' => 30, // acquis d'office : seules les cultures de la zone sont évaluées
        'temperature_points' => 40,
        'humidity_points' => 20,
        'rainfall_points' => 10,
        'level_thresholds' => [
            'recommended' => 70,
            'moderate' => 45,
            // en dessous de 'moderate' => 'unfavorable'
        ],
    ],

    /*
     |----------------------------------------------------------------------
     | Seuils de détection des risques météo génériques (indépendants de la culture)
     |----------------------------------------------------------------------
     */
    'risk_thresholds' => [
        'high_humidity' => 85,       // % moyen sur la fenêtre -> risque de maladies cryptogamiques
        'heavy_rainfall_mm_day' => 15, // mm/j moyen -> risque d'excès d'eau / inondation
        'drought_mm_day' => 0.5,      // mm/j moyen -> risque de stress hydrique
        'strong_wind_kmh' => 40,      // km/h -> risque de verse / dommages mécaniques
        'heat_stress_c' => 34,        // °C max -> risque de stress thermique
        'cold_stress_c' => 8,         // °C min -> risque de froid pour cultures sensibles
    ],

    /*
     |----------------------------------------------------------------------
     | Fenêtre d'analyse autour de la date choisie (jours avant / après), cahier des charges §8
     |----------------------------------------------------------------------
     */
    'analysis_window_days' => 3,
];
