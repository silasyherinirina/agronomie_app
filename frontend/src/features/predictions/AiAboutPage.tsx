import type { ReactNode } from 'react'
import {
  AlertTriangle,
  ArrowRight,
  BarChart3,
  Brain,
  CheckCircle2,
  Cog,
  Database,
  Gauge,
  ListChecks,
  Sprout,
  Target,
  type LucideIcon,
} from 'lucide-react'

/**
 * Page pédagogique "À propos de l'IA" (cahier des charges §26). Tout le contenu ci-dessous
 * reprend, sans les modifier, les faits vérifiés documentés dans
 * docs/machine-learning/phase9-pipeline.md — dernière exécution réelle du pipeline le
 * 2026-08-29. Ne jamais mettre ces chiffres à jour ici sans les revérifier à la source.
 */
export function AiAboutPage() {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-ink">À propos du modèle de prévision</h1>
        <p className="text-sm text-muted">
          Comment la prévision de rendement est obtenue, sur quelles données, avec quelles limites.
        </p>
      </div>

      <Section title="Données synthétiques, pas réelles" icon={AlertTriangle} tone="warning">
        <p>
          Aucune donnée agricole réelle de Madagascar n'était disponible au moment du développement de ce modèle. Un jeu
          de données <strong>entièrement synthétique</strong> a été généré par une formule mathématique documentée
          (600 observations, graine aléatoire fixée à 42 pour la reproductibilité). Ces données ne représentent
          <strong> aucune exploitation agricole réelle</strong>. Toute prédiction produite par ce modèle est explicitement
          marquée comme expérimentale et ne doit jamais être interprétée comme une prévision agronomique réelle.
        </p>
      </Section>

      <Section title="Objectif du modèle" icon={Target}>
        <p>Prévoir le rendement du riz (kg/ha) à partir de cinq variables agronomiques simples.</p>
      </Section>

      <Section title="Comment fonctionne la prévision ?" icon={Cog}>
        <div className="flex flex-wrap items-center justify-center gap-2 rounded-lg bg-canvas p-4 sm:gap-3">
          <FlowStep icon={Database} label="Données agricoles" />
          <ArrowRight size={18} className="flex-shrink-0 text-muted" aria-hidden />
          <FlowStep icon={ListChecks} label="Prétraitement" />
          <ArrowRight size={18} className="flex-shrink-0 text-muted" aria-hidden />
          <FlowStep icon={Brain} label="Modèle Machine Learning" />
          <ArrowRight size={18} className="flex-shrink-0 text-muted" aria-hidden />
          <FlowStep icon={Sprout} label="Prédiction du rendement" />
        </div>
        <p className="mt-4">
          Le modèle sélectionné est sauvegardé et rechargé pour chaque demande de prédiction. Étant donné les variables
          d'une parcelle (superficie, engrais, pluviométrie, mois de plantation, variété), il renvoie un rendement
          estimé en kg/ha, accompagné de ses métriques de test, de l'algorithme utilisé, de la date, et — lorsque
          calculable — de l'importance relative de chaque variable dans la décision du modèle.
        </p>
      </Section>

      <Section title="Variables utilisées" icon={ListChecks}>
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
              <th className="py-1.5 pr-3">Variable</th>
              <th className="py-1.5 pr-3">Type</th>
              <th className="py-1.5">Rôle</th>
            </tr>
          </thead>
          <tbody>
            {[
              ['Superficie (ha)', 'numérique', 'Superficie de la parcelle'],
              ['Engrais (kg/ha)', 'numérique', "Quantité d'engrais utilisée"],
              ['Pluviométrie (mm)', 'numérique', 'Cumulée sur le cycle — anticipe la météo réelle (Module L)'],
              ['Mois de plantation', 'numérique (1-12)', 'Proxy de saisonnalité'],
              ['Variété', 'catégorielle', 'traditionnelle ou améliorée'],
            ].map(([name, type, role]) => (
              <tr key={name} className="border-b border-line last:border-0">
                <td className="py-1.5 pr-3 text-ink">{name}</td>
                <td className="py-1.5 pr-3 text-muted">{type}</td>
                <td className="py-1.5 text-muted">{role}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Section>

      <Section title="Algorithmes comparés" icon={BarChart3}>
        <p className="mb-2">
          Trois algorithmes ont été entraînés et comparés objectivement sur un jeu de test (20 % des données, jamais vu à
          l'entraînement) :
        </p>
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
              <th className="py-1.5 pr-3">Algorithme</th>
              <th className="py-1.5 pr-3">MAE (kg/ha)</th>
              <th className="py-1.5 pr-3">RMSE (kg/ha)</th>
              <th className="py-1.5">R² (test)</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-b border-line bg-success-bg/40">
              <td className="flex items-center gap-1.5 py-1.5 pr-3 font-semibold text-ink">
                <CheckCircle2 size={14} className="text-primary" aria-hidden />
                Régression linéaire (retenu)
              </td>
              <td className="py-1.5 pr-3">138.95</td>
              <td className="py-1.5 pr-3">167.01</td>
              <td className="py-1.5 font-semibold">0.5381</td>
            </tr>
            <tr className="border-b border-line">
              <td className="py-1.5 pr-3 text-ink">Gradient Boosting</td>
              <td className="py-1.5 pr-3">135.72</td>
              <td className="py-1.5 pr-3">170.24</td>
              <td className="py-1.5">0.5201</td>
            </tr>
            <tr>
              <td className="py-1.5 pr-3 text-ink">Random Forest</td>
              <td className="py-1.5 pr-3">140.74</td>
              <td className="py-1.5 pr-3">172.19</td>
              <td className="py-1.5">0.5090</td>
            </tr>
          </tbody>
        </table>
        <p className="mt-3 text-sm text-muted">
          C'est la régression linéaire, le modèle le plus simple des trois, qui obtient le meilleur R² sur le jeu de
          test — pas un modèle d'ensemble plus sophistiqué. Ce résultat est conservé tel quel : la règle de sélection
          (meilleur R² sur le test) est appliquée systématiquement, jamais ajustée a posteriori pour favoriser un
          modèle plus complexe. R² ≈ 0,54 signifie qu'environ 54 % de la variance du rendement est expliquée par les 5
          variables retenues — un résultat honnête compte tenu du bruit délibérément injecté dans les données
          synthétiques, pas un score artificiellement gonflé.
        </p>
      </Section>

      <Section title="Niveau de confiance" icon={Gauge}>
        <p>
          Aucun « niveau de confiance » n'est affiché avec les prédictions. Son calcul rigoureux (intervalle de
          prédiction statistique) n'a pas été implémenté dans le périmètre de ce mémoire — il est préférable de
          l'omettre plutôt que d'afficher un chiffre inventé.
        </p>
      </Section>

      <Section title="Limites" icon={AlertTriangle} tone="warning">
        <ul className="list-disc space-y-1 pl-5">
          <li>Données synthétiques : aucune validité agronomique réelle.</li>
          <li>Une seule culture modélisée (riz).</li>
          <li>Pas de validation croisée (k-fold) — un simple split train/test a été jugé suffisant pour ce périmètre.</li>
          <li>Hyperparamètres fixés manuellement, non optimisés automatiquement.</li>
          <li>Le remplacement par des données réelles nécessitera un nouveau ré-entraînement, sans changer l'architecture.</li>
        </ul>
      </Section>
    </div>
  )
}

function FlowStep({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
  return (
    <div className="flex w-24 flex-col items-center gap-1.5 text-center">
      <span className="flex h-10 w-10 items-center justify-center rounded-md bg-primary-light text-primary">
        <Icon size={18} aria-hidden />
      </span>
      <span className="text-xs text-muted">{label}</span>
    </div>
  )
}

function Section({
  title,
  icon: Icon,
  tone = 'default',
  children,
}: {
  title: string
  icon: LucideIcon
  tone?: 'default' | 'warning'
  children: ReactNode
}) {
  return (
    <section className="rounded-lg border border-line bg-surface p-5 shadow-sm">
      <h2 className="mb-3 flex items-center gap-2 text-base font-semibold text-ink">
        <Icon size={18} className={tone === 'warning' ? 'text-warning' : 'text-primary'} aria-hidden />
        {title}
      </h2>
      <div className="text-sm leading-relaxed text-ink">{children}</div>
    </section>
  )
}
