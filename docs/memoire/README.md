# Éléments techniques du mémoire

Ce dossier rassemble, rédigés en registre académique et prêts à être repris (copiés,
adaptés, complétés par la partie théorique/bibliographique propre au mémoire), les éléments
techniques produits pendant le développement de la plateforme. Il **ne remplace pas** la
documentation technique opérationnelle (installation, API, etc.), qui reste dans les autres
dossiers de `docs/` et les `README.md` de chaque application — il en est la synthèse narrative.

## Plan proposé (correspondance avec un plan de mémoire M2 classique)

| Chapitre du mémoire | Fichier | Contenu |
|---|---|---|
| Introduction / Présentation du système | [`01-presentation-systeme.md`](01-presentation-systeme.md) | Contexte, problématique, objectifs, utilisateurs |
| Conception | [`02-conception.md`](02-conception.md) | Besoins, architecture, UML, MCD/MLD |
| Choix technologiques | [`03-choix-technologiques.md`](03-choix-technologiques.md) | Justification de chaque brique de la stack |
| Méthodologie | [`04-methodologie.md`](04-methodologie.md) | Démarche de développement, méthodologie ML |
| Implémentation | [`05-implementation.md`](05-implementation.md) | Réalisation par module, points techniques clés |
| Tests et résultats | [`06-tests-resultats.md`](06-tests-resultats.md) | Stratégie, résultats quantitatifs, résultats ML réels |
| Limites et perspectives | [`07-limites-perspectives.md`](07-limites-perspectives.md) | Limites assumées, pistes d'évolution |

## Règle suivie dans la rédaction

Conformément à la règle anti-hallucination appliquée pendant tout le développement (cahier
des charges §24), **chaque chiffre, résultat ou métrique cité dans ces chapitres a été vérifié
dans le code, les journaux de tests ou une exécution réelle** avant d'être écrit — jamais
estimé ni arrondi de mémoire. Les sources primaires (fichiers de test, documents techniques
détaillés) sont systématiquement référencées pour permettre une vérification indépendante
(utile pour la soutenance).

## Illustrations et diagrammes

Les diagrammes UML/MCD sont fournis en sources **Mermaid** (`.mmd`) et **PlantUML** (`.puml`)
dans [`docs/uml/`](../uml/), pas en images. Pour les intégrer au document du mémoire (Word,
LaTeX) :
- Mermaid : rendu via l'extension VS Code "Markdown Preview Mermaid Support", ou
  [mermaid.live](https://mermaid.live) (coller le contenu du fichier, exporter en PNG/SVG).
- PlantUML : rendu via l'extension VS Code "PlantUML", ou un serveur PlantUML local/en ligne.

Aucune capture d'écran de l'interface n'est fournie ici : les copies d'écran de l'application
réelle (formulaires, carte, tableau de bord) sont à réaliser directement depuis l'application
en fonctionnement (`npm run dev` + `php artisan serve`), pour éviter toute image reconstituée
qui ne refléterait pas fidèlement l'état réel du système.
