import { useState, type ReactNode } from 'react'
import { Link, Navigate } from 'react-router-dom'
import {
  Sprout,
  Menu,
  X,
  Users,
  Building2,
  MapPinned,
  Wheat,
  CalendarRange,
  Boxes,
  Map as MapIcon,
  CloudSun,
  BrainCircuit,
  LayoutDashboard,
  Database,
  TrendingUp,
  ClipboardList,
  ArrowRight,
  ThermometerSun,
  Droplets,
  Wind,
  CheckCircle2,
} from 'lucide-react'
import { useAuth } from '../hooks/useAuth'
import { Button } from '../components/Button'

const NAV_LINKS = [
  { href: '#fonctionnalites', label: 'Fonctionnalités' },
  { href: '#fonctionnement', label: 'Comment ça marche' },
  { href: '#ia', label: 'Intelligence artificielle' },
  { href: '#meteo', label: 'Météo' },
  { href: '#a-propos', label: 'À propos' },
]

/**
 * Page d'accueil publique (audit de professionnalisation). Un visiteur non authentifié qui
 * arrive sur "/" voit cette page — elle ne redirige plus automatiquement vers /login.
 * Un utilisateur déjà connecté est renvoyé vers /dashboard (pas d'intérêt à lui montrer une
 * page marketing).
 */
export function HomePage() {
  const { user, loading } = useAuth()

  if (!loading && user) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="min-h-full bg-canvas text-ink">
      <HomeNavbar />
      <Hero />
      <ProblemSection />
      <FeaturesSection />
      <HowItWorksSection />
      <AiSection />
      <WeatherSection />
      <DashboardPreviewSection />
      <WhySection />
      <FinalCtaSection />
      <HomeFooter />
    </div>
  )
}

function HomeNavbar() {
  const [open, setOpen] = useState(false)

  return (
    <header className="sticky top-0 z-30 border-b border-line bg-surface/80 backdrop-blur-md">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
        <Link to="/" className="flex items-center gap-2 text-ink no-underline">
          <span className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-white">
            <Sprout size={20} aria-hidden />
          </span>
          <span className="text-base font-semibold">Agriculture Mada</span>
        </Link>

        <nav className="hidden items-center gap-6 lg:flex" aria-label="Navigation principale">
          {NAV_LINKS.map((link) => (
            <a key={link.href} href={link.href} className="text-sm font-medium text-muted transition-colors hover:text-ink">
              {link.label}
            </a>
          ))}
        </nav>

        <div className="hidden items-center gap-3 lg:flex">
          <Link to="/login">
            <Button variant="ghost">Connexion</Button>
          </Link>
          <Link to="/register">
            <Button variant="primary">Créer un compte</Button>
          </Link>
        </div>

        <button
          type="button"
          aria-label={open ? 'Fermer le menu' : 'Ouvrir le menu'}
          aria-expanded={open}
          className="rounded-md p-2 text-ink hover:bg-canvas lg:hidden"
          onClick={() => setOpen((o) => !o)}
        >
          {open ? <X size={22} /> : <Menu size={22} />}
        </button>
      </div>

      {open && (
        <div className="border-t border-line bg-surface px-4 py-4 lg:hidden">
          <nav className="flex flex-col gap-3" aria-label="Navigation mobile">
            {NAV_LINKS.map((link) => (
              <a key={link.href} href={link.href} className="text-sm font-medium text-ink" onClick={() => setOpen(false)}>
                {link.label}
              </a>
            ))}
          </nav>
          <div className="mt-4 flex flex-col gap-2">
            <Link to="/login" onClick={() => setOpen(false)}>
              <Button variant="secondary" className="w-full">
                Connexion
              </Button>
            </Link>
            <Link to="/register" onClick={() => setOpen(false)}>
              <Button variant="primary" className="w-full">
                Créer un compte
              </Button>
            </Link>
          </div>
        </div>
      )}
    </header>
  )
}

function Hero() {
  return (
    <section className="relative overflow-hidden bg-gradient-to-b from-primary-light to-canvas">
      <div className="mx-auto flex max-w-6xl flex-col items-center gap-8 px-4 py-20 text-center sm:px-6 lg:py-28">
        <span className="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-muted">
          <Sprout size={14} className="text-primary" aria-hidden />
          Plateforme agricole intelligente — Madagascar
        </span>

        <h1 className="max-w-3xl text-4xl font-bold tracking-tight text-ink sm:text-5xl">
          Pilotez votre agriculture avec intelligence
        </h1>

        <p className="max-w-2xl text-lg text-muted">
          Une plateforme intelligente pour gérer les coopératives, les parcelles, les cultures et
          les récoltes, et anticiper les rendements grâce aux données et à l'intelligence
          artificielle.
        </p>

        <div className="flex flex-col gap-3 sm:flex-row">
          <Link to="/register">
            <Button variant="primary" className="px-6 py-3 text-base">
              Commencer maintenant
              <ArrowRight size={18} />
            </Button>
          </Link>
          <a href="#fonctionnalites">
            <Button variant="secondary" className="px-6 py-3 text-base">
              Découvrir la plateforme
            </Button>
          </a>
        </div>

        <div className="mt-6 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-muted">
          <span className="flex items-center gap-1.5">
            <Database size={16} className="text-primary" aria-hidden /> Données centralisées
          </span>
          <span className="flex items-center gap-1.5">
            <MapPinned size={16} className="text-primary" aria-hidden /> Cartographie PostGIS
          </span>
          <span className="flex items-center gap-1.5">
            <BrainCircuit size={16} className="text-primary" aria-hidden /> Prévisions par IA
          </span>
        </div>
      </div>
    </section>
  )
}

function ProblemSection() {
  const points = [
    'Suivi dispersé des parcelles et des cultures',
    'Récoltes et intrants enregistrés sur des supports séparés',
    'Absence de vision consolidée par coopérative',
    'Difficulté à analyser les performances agricoles',
    'Peu d’outils pour anticiper la production à venir',
  ]

  return (
    <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <div className="grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
          <h2 className="text-2xl font-semibold text-ink sm:text-3xl">
            Une gestion agricole encore trop dispersée
          </h2>
          <p className="mt-3 text-muted">
            De nombreuses coopératives agricoles manquent d'outils centralisés pour suivre leur
            activité au quotidien :
          </p>
        </div>
        <ul className="flex flex-col gap-3">
          {points.map((point) => (
            <li key={point} className="flex items-start gap-3 rounded-lg border border-line bg-surface p-4 text-sm text-ink shadow-sm">
              <span className="mt-0.5 h-2 w-2 flex-shrink-0 rounded-full bg-accent" aria-hidden />
              {point}
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}

const FEATURES = [
  { icon: Building2, title: 'Gestion des coopératives', description: 'Centralisez les informations, la localisation et les responsables de chaque coopérative.' },
  { icon: Users, title: 'Gestion des agriculteurs', description: 'Suivez les agriculteurs membres, leur statut et leurs parcelles.' },
  { icon: MapPinned, title: 'Gestion des parcelles', description: 'Géolocalisez les parcelles réelles avec PostGIS et calculez leur superficie exacte.' },
  { icon: Sprout, title: 'Gestion des cultures', description: 'Référentiel des cultures et variétés pratiquées par vos coopératives.' },
  { icon: CalendarRange, title: 'Campagnes agricoles', description: 'Organisez le suivi par campagne et par saison.' },
  { icon: Wheat, title: 'Suivi des récoltes', description: 'Enregistrez quantités, qualité et rendement de chaque récolte.' },
  { icon: Boxes, title: 'Gestion des intrants', description: 'Suivez semences, engrais et produits phytosanitaires, avec leur coût.' },
  { icon: MapIcon, title: 'Cartographie agricole', description: 'Visualisez toutes vos parcelles sur une carte interactive.' },
  { icon: CloudSun, title: 'Prévisions météorologiques', description: 'Conditions actuelles et prévisions à court terme par parcelle.' },
  { icon: BrainCircuit, title: 'Prévisions de rendement par IA', description: 'Estimation de rendement basée sur un modèle entraîné et évalué.' },
  { icon: LayoutDashboard, title: 'Tableau de bord analytique', description: 'Indicateurs clés, graphiques et statistiques calculés en temps réel.' },
] as const

function FeaturesSection() {
  return (
    <section id="fonctionnalites" className="bg-surface py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-2xl font-semibold text-ink sm:text-3xl">Tout ce qu'il faut pour piloter une coopérative</h2>
          <p className="mt-3 text-muted">Onze modules pour couvrir l'ensemble du cycle agricole, de la parcelle à la prévision.</p>
        </div>

        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map(({ icon: Icon, title, description }) => (
            <div
              key={title}
              className="group flex flex-col gap-3 rounded-lg border border-line bg-canvas p-5 transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md"
            >
              <span className="flex h-10 w-10 items-center justify-center rounded-md bg-primary-light text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                <Icon size={20} aria-hidden />
              </span>
              <h3 className="text-base font-semibold text-ink">{title}</h3>
              <p className="text-sm text-muted">{description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

const STEPS = [
  { title: 'Créer votre coopérative', description: "Enregistrez la coopérative, sa localisation et son responsable." },
  { title: 'Ajouter agriculteurs et parcelles', description: 'Rattachez les membres et géolocalisez leurs parcelles.' },
  { title: 'Suivre cultures et campagnes', description: 'Planifiez les cycles culturaux campagne après campagne.' },
  { title: 'Enregistrer récoltes et intrants', description: 'Consignez la production et les ressources utilisées.' },
  { title: 'Analyser et anticiper', description: 'Consultez le tableau de bord et les prévisions de rendement.' },
]

function HowItWorksSection() {
  return (
    <section id="fonctionnement" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <div className="mx-auto max-w-2xl text-center">
        <h2 className="text-2xl font-semibold text-ink sm:text-3xl">Comment ça marche</h2>
        <p className="mt-3 text-muted">Cinq étapes pour structurer le suivi agricole de votre coopérative.</p>
      </div>

      <ol className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
        {STEPS.map((step, i) => (
          <li key={step.title} className="flex flex-col gap-2 rounded-lg border border-line bg-surface p-5">
            <span className="text-2xl font-bold text-primary">{String(i + 1).padStart(2, '0')}</span>
            <h3 className="text-sm font-semibold text-ink">{step.title}</h3>
            <p className="text-xs text-muted">{step.description}</p>
          </li>
        ))}
      </ol>
    </section>
  )
}

function AiSection() {
  return (
    <section id="ia" className="bg-primary-dark py-16 text-white">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
        <div>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium">
            <BrainCircuit size={14} aria-hidden /> Service Machine Learning indépendant
          </span>
          <h2 className="mt-4 text-2xl font-semibold sm:text-3xl">Une intelligence artificielle transparente</h2>
          <p className="mt-3 text-white/85">
            Un service dédié (Python / FastAPI / scikit-learn) analyse les caractéristiques des
            parcelles et des cultures pour produire une estimation de rendement. Le modèle
            utilisé, ses métriques et les variables prises en compte sont toujours affichés avec
            le résultat.
          </p>
          <div className="mt-5 rounded-lg border border-white/20 bg-white/5 p-4 text-sm text-white/90">
            ⚠️ Le modèle actuellement en production est entraîné sur des{' '}
            <strong>données synthétiques</strong> (aucune donnée agricole réelle de Madagascar
            n'était disponible au moment du développement). Chaque prédiction est présentée comme
            expérimentale, jamais comme une prévision agronomique certaine.
          </div>
          <Link to="/login" className="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-white hover:underline">
            Découvrir les prévisions IA <ArrowRight size={16} />
          </Link>
        </div>

        <div className="flex flex-col gap-3">
          {[
            { label: 'Modèles comparés', value: 'Régression linéaire, Random Forest, Gradient Boosting' },
            { label: 'Évaluation', value: 'MAE, RMSE, R² — mesurées sur données de test' },
            { label: 'Sélection', value: 'Le modèle retenu est celui qui obtient le meilleur score, jamais un choix arbitraire' },
            { label: 'Transparence', value: "Variables importantes affichées avec chaque prédiction, quand disponibles" },
          ].map((row) => (
            <div key={row.label} className="rounded-lg border border-white/15 bg-white/5 p-4">
              <div className="text-xs font-medium uppercase tracking-wide text-white/60">{row.label}</div>
              <div className="mt-1 text-sm text-white">{row.value}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function WeatherSection() {
  return (
    <section id="meteo" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <div className="grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
          <h2 className="text-2xl font-semibold text-ink sm:text-3xl">Une météo utile au suivi agricole</h2>
          <p className="mt-3 text-muted">
            Pour chaque parcelle, la plateforme combine les conditions météorologiques réelles
            (température, précipitations, humidité, vent) et des prévisions à court terme avec les
            observations de terrain saisies par les coopératives, pour resituer récoltes et
            plantations dans leur contexte climatique.
          </p>
          <p className="mt-3 text-xs text-muted">
            Données fournies par Open-Meteo, un service météorologique public. Aperçu illustratif
            ci-contre — les valeurs réelles s'affichent dans l'application, par parcelle.
          </p>
        </div>

        <div className="rounded-xl border border-line bg-surface p-6 shadow-sm">
          <div className="flex items-center justify-between text-sm text-muted">
            <span>Aperçu du module météo</span>
            <CloudSun size={20} className="text-primary" aria-hidden />
          </div>
          <div className="mt-4 grid grid-cols-3 gap-4 text-center">
            <div className="flex flex-col items-center gap-1">
              <ThermometerSun size={22} className="text-accent" aria-hidden />
              <span className="text-xs text-muted">Température</span>
            </div>
            <div className="flex flex-col items-center gap-1">
              <Droplets size={22} className="text-primary" aria-hidden />
              <span className="text-xs text-muted">Précipitations</span>
            </div>
            <div className="flex flex-col items-center gap-1">
              <Wind size={22} className="text-muted" aria-hidden />
              <span className="text-xs text-muted">Vent &amp; humidité</span>
            </div>
          </div>
          <div className="mt-5 flex justify-between gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex flex-1 flex-col items-center gap-1 rounded-md bg-canvas p-2">
                <span className="text-[10px] text-muted">J{i === 0 ? '' : `+${i}`}</span>
                <CloudSun size={16} className="text-primary" aria-hidden />
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

function DashboardPreviewSection() {
  const panels = [
    { icon: LayoutDashboard, label: 'Indicateurs clés (KPI)' },
    { icon: TrendingUp, label: 'Production & rendement' },
    { icon: MapIcon, label: 'Carte analytique' },
    { icon: CloudSun, label: 'Météo par parcelle' },
    { icon: BrainCircuit, label: 'Prévisions IA' },
    { icon: ClipboardList, label: 'Suivi des activités' },
  ]

  return (
    <section className="bg-surface py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-2xl font-semibold text-ink sm:text-3xl">Un tableau de bord complet</h2>
          <p className="mt-3 text-muted">
            Aperçu schématique des espaces disponibles une fois connecté — les indicateurs
            affichés dans l'application proviennent toujours de vos données réelles.
          </p>
        </div>

        <div className="mx-auto mt-10 grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-3">
          {panels.map(({ icon: Icon, label }) => (
            <div key={label} className="flex flex-col items-center gap-2 rounded-lg border border-line bg-canvas p-5 text-center">
              <Icon size={24} className="text-primary" aria-hidden />
              <span className="text-xs font-medium text-ink">{label}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

const REASONS = [
  'Centralisation de toutes les données agricoles',
  'Meilleure visibilité sur l’activité des coopératives',
  'Suivi géographique précis des parcelles',
  'Aide à la décision par des indicateurs réels',
  'Analyse des performances par culture et campagne',
  'Anticipation de la production à venir',
]

function WhySection() {
  return (
    <section id="a-propos" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <div className="grid gap-10 lg:grid-cols-2">
        <div>
          <h2 className="text-2xl font-semibold text-ink sm:text-3xl">Pourquoi Agriculture Mada ?</h2>
          <p className="mt-3 text-muted">
            Agriculture Mada est une plateforme conçue pour structurer la gestion des coopératives
            agricoles à Madagascar, développée dans le cadre d'un mémoire de Master 2 associant
            gestion de données, cartographie et intelligence artificielle.
          </p>
        </div>
        <ul className="grid gap-3 sm:grid-cols-2">
          {REASONS.map((reason) => (
            <li key={reason} className="flex items-start gap-2 text-sm text-ink">
              <CheckCircle2 size={18} className="mt-0.5 flex-shrink-0 text-primary" aria-hidden />
              {reason}
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}

function FinalCtaSection() {
  return (
    <section className="bg-primary py-16 text-white">
      <div className="mx-auto flex max-w-3xl flex-col items-center gap-5 px-4 text-center sm:px-6">
        <h2 className="text-2xl font-semibold sm:text-3xl">Prêt à mieux piloter votre coopérative ?</h2>
        <p className="text-white/90">Centralisez vos données agricoles et prenez de meilleures décisions.</p>
        <div className="flex flex-col gap-3 sm:flex-row">
          <Link to="/register">
            <Button variant="secondary" className="px-6 py-3 text-base">
              Créer un compte
            </Button>
          </Link>
          <Link to="/login">
            <Button variant="ghost" className="px-6 py-3 text-base text-white hover:bg-white/10">
              Se connecter
            </Button>
          </Link>
        </div>
      </div>
    </section>
  )
}

function FooterColumn({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div>
      <h4 className="text-xs font-semibold uppercase tracking-wide text-white/50">{title}</h4>
      <div className="mt-3 flex flex-col gap-2 text-sm text-white/80">{children}</div>
    </div>
  )
}

function HomeFooter() {
  return (
    <footer className="bg-ink py-12 text-white">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 sm:px-6 md:grid-cols-3">
        <div>
          <div className="flex items-center gap-2">
            <Sprout size={20} aria-hidden />
            <span className="text-base font-semibold">Agriculture Mada</span>
          </div>
          <p className="mt-2 text-sm text-white/70">Plateforme intelligente de gestion agricole</p>
        </div>

        <FooterColumn title="Navigation">
          <a href="#" className="hover:underline">Accueil</a>
          <a href="#fonctionnalites" className="hover:underline">Fonctionnalités</a>
          <a href="#meteo" className="hover:underline">Météo</a>
          <a href="#ia" className="hover:underline">Intelligence artificielle</a>
          <a href="#a-propos" className="hover:underline">À propos</a>
        </FooterColumn>

        <FooterColumn title="Compte">
          <Link to="/login" className="hover:underline">Connexion</Link>
          <Link to="/register" className="hover:underline">Créer un compte</Link>
        </FooterColumn>
      </div>
      <div className="mx-auto mt-8 max-w-6xl border-t border-white/10 px-4 pt-6 text-xs text-white/50 sm:px-6">
        © 2026 Agriculture Mada
      </div>
    </footer>
  )
}
