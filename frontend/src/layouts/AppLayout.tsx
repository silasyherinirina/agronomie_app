import { useEffect, useRef, useState, type ComponentType } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import {
  Banknote,
  Bell,
  Boxes,
  Building2,
  BrainCircuit,
  CalendarRange,
  ChevronDown,
  CloudSun,
  FileBarChart,
  LayoutDashboard,
  LogOut,
  Map as MapIcon,
  Menu,
  PanelLeftClose,
  PanelLeftOpen,
  RefreshCw,
  Settings,
  Sprout,
  Square,
  Users,
  Wheat,
} from "lucide-react";
import { useAuth } from "../hooks/useAuth";
import { useNotifications } from "../hooks/useNotifications";
import { notificationIcon } from "../features/notifications/notificationIcon";
import { formatRelativeTime } from "../utils/relativeTime";
import type { AppNotification, Role } from "../types/api";

interface NavItem {
  to: string;
  label: string;
  icon: ComponentType<{ size?: number }>;
  end?: boolean;
  /** Rôles autorisés à voir ce lien. Absent = visible par tous les rôles authentifiés. */
  roles?: Role[];
}

interface NavGroup {
  /** null pour le premier groupe (Tableau de bord seul) — pas de libellé de section flottant. */
  label: string | null;
  items: NavItem[];
}

// Phase D, §8 : "L'interface doit adapter les fonctionnalités au rôle." La navigation reflète
// ici ce qu'un rôle peut consulter ; le contrôle réel (création/modification/suppression) reste
// entièrement appliqué côté Laravel (Policies) — cette liste n'est qu'un confort d'affichage,
// jamais une frontière de sécurité (cahier des charges §8 — Laravel reste seul juge final).
// Regroupement (§ refonte Dashboard) inspiré de la maquette de référence : sections nommées au
// lieu d'une liste plate, pour une hiérarchie de navigation plus lisible sur ~13 destinations.
const NAV_GROUPS: NavGroup[] = [
  {
    label: null,
    items: [
      { to: "/dashboard", label: "Tableau de bord", icon: LayoutDashboard },
    ],
  },
  {
    label: "Gestion agricole",
    items: [
      {
        to: "/cooperatives",
        label: "Coopératives",
        icon: Building2,
        roles: ["admin", "cooperative_manager", "analyst"],
      },
      {
        to: "/farmers",
        label: "Agriculteurs",
        icon: Users,
        roles: ["admin", "cooperative_manager", "analyst"],
      },
      { to: "/plots", label: "Parcelles", icon: Square },
      { to: "/crops", label: "Cultures & variétés", icon: Sprout },
      { to: "/campaigns", label: "Campagnes agricoles", icon: CalendarRange },
      { to: "/crop-cycles", label: "Cycles culturaux", icon: RefreshCw },
      { to: "/harvests", label: "Récoltes", icon: Wheat },
      { to: "/sales", label: "Ventes", icon: Banknote },
      {
        to: "/inputs",
        label: "Intrants",
        icon: Boxes,
        roles: ["admin", "cooperative_manager", "analyst"],
      },
    ],
  },
  {
    label: "Analyse & intelligence",
    items: [
      { to: "/map", label: "Carte", icon: MapIcon },
      { to: "/predictions", label: "Prévisions IA", icon: BrainCircuit },
      { to: "/weather", label: "Météo Agricole", icon: CloudSun },
      { to: "/reports", label: "Rapports", icon: FileBarChart },
    ],
  },
  {
    label: "Compte",
    items: [{ to: "/profile", label: "Paramètres", icon: Settings }],
  },
];

const ALL_ITEMS = NAV_GROUPS.flatMap((g) => g.items);

const ROLE_LABELS: Record<string, string> = {
  admin: "Administrateur",
  cooperative_manager: "Responsable de coopérative",
  farmer: "Agriculteur",
  analyst: "Analyste",
};

const SIDEBAR_COLLAPSE_KEY = "agriculture-mada:sidebar-collapsed";

export function AppLayout() {
  const { user, logout } = useAuth();
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(() => {
    try {
      return localStorage.getItem(SIDEBAR_COLLAPSE_KEY) === "1";
    } catch {
      return false;
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem(SIDEBAR_COLLAPSE_KEY, collapsed ? "1" : "0");
    } catch {
      // Confort d'affichage uniquement — l'indisponibilité du stockage (mode privé, quota) ne
      // doit jamais empêcher l'utilisation du tableau de bord.
    }
  }, [collapsed]);

  const visibleGroups = NAV_GROUPS.map((group) => ({
    ...group,
    items: group.items.filter(
      (item) => !item.roles || (user && item.roles.includes(user.role)),
    ),
  })).filter((group) => group.items.length > 0);

  return (
    // h-screen + overflow-hidden : borne le layout entier à la hauteur de la fenêtre une bonne
    // fois pour toutes. Sans ça, rien n'empêche ce conteneur (et la sidebar, étirée sur sa
    // hauteur) de grandir avec le contenu de la page — c'est <body> qui finissait par défiler.
    <div className="flex h-screen overflow-hidden bg-canvas p-0 lg:gap-3 lg:p-3">
      {/* Fond d'écran mobile derrière le tiroir de navigation */}
      {mobileNavOpen && (
        <div
          className="fixed inset-0 z-20 bg-black/40 lg:hidden"
          onClick={() => setMobileNavOpen(false)}
        />
      )}

      <aside
        className={[
          // Mobile (< lg) : tiroir détaché du flux (fixed), glissé hors écran par défaut, coins
          // carrés (plein écran en hauteur). Desktop (lg+) : carte flottante arrondie, encadrée
          // par le padding du conteneur parent — inspirée de la maquette de référence.
          "fixed inset-y-0 left-0 z-30 flex w-64 flex-shrink-0 flex-col bg-sidebar text-white transition-[transform,width]",
          "lg:static lg:h-full lg:translate-x-0 lg:rounded-2xl lg:shadow-md",
          collapsed ? "lg:w-20" : "lg:w-64",
          mobileNavOpen ? "translate-x-0" : "-translate-x-full",
        ].join(" ")}
      >
        <div className="flex items-center gap-2 border-b border-white/10 px-4 py-5">
          <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10 text-white">
            <Sprout size={18} aria-hidden />
          </span>
          {!collapsed && (
            <span className="truncate text-[1.05rem] font-bold">
              Agriculture Mada
            </span>
          )}
          <button
            type="button"
            onClick={() => setCollapsed((c) => !c)}
            aria-label={
              collapsed ? "Déplier la navigation" : "Réduire la navigation"
            }
            className="ml-auto hidden flex-shrink-0 rounded-md p-1.5 text-white/60 hover:bg-white/10 hover:text-white lg:inline-flex"
          >
            {collapsed ? (
              <PanelLeftOpen size={16} aria-hidden />
            ) : (
              <PanelLeftClose size={16} aria-hidden />
            )}
          </button>
        </div>

        <nav className="flex flex-1 flex-col gap-4 overflow-y-auto p-3">
          {visibleGroups.map((group, gi) => (
            <div
              key={group.label ?? `group-${gi}`}
              className="flex flex-col gap-0.5"
            >
              {group.label && !collapsed && (
                <p className="mb-1 px-3 text-[0.68rem] font-semibold uppercase tracking-wider text-white/40">
                  {group.label}
                </p>
              )}
              {group.items.map((item) => {
                const Icon = item.icon;
                return (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.end}
                    title={collapsed ? item.label : undefined}
                    onClick={() => setMobileNavOpen(false)}
                    className={({ isActive }) =>
                      [
                        "flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors",
                        collapsed ? "lg:justify-center" : "",
                        isActive
                          ? "bg-white font-semibold text-primary-dark"
                          : "text-white/80 hover:bg-sidebar-hover hover:text-white",
                      ].join(" ")
                    }
                  >
                    <Icon size={18} aria-hidden />
                    <span className={collapsed ? "lg:hidden" : ""}>
                      {item.label}
                    </span>
                  </NavLink>
                );
              })}
            </div>
          ))}
        </nav>

        <div className="border-t border-white/10 p-3">
          <button
            type="button"
            onClick={() => void logout()}
            title={collapsed ? "Déconnexion" : undefined}
            className={[
              "flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm text-white/80 transition-colors hover:bg-sidebar-hover hover:text-white",
              collapsed ? "lg:justify-center" : "",
            ].join(" ")}
          >
            <LogOut size={18} aria-hidden />
            <span className={collapsed ? "lg:hidden" : ""}>Déconnexion</span>
          </button>
        </div>
      </aside>

      {/* min-h-0 : piège classique flexbox — sans lui, cette colonne grandirait pour englober
          tout le contenu de <main> au lieu de le laisser défiler en interne (elle hériterait
          sinon d'un min-height:auto qui l'empêche de "rétrécir" sous la taille de son contenu). */}
      <div className="flex min-h-0 min-w-0 flex-1 flex-col">
        <header className="flex h-16 flex-shrink-0 items-center justify-between border-b border-line bg-surface px-4 sm:px-6 lg:rounded-2xl lg:border lg:shadow-sm">
          <div className="flex items-center gap-3">
            <button
              type="button"
              aria-label="Ouvrir la navigation"
              className="rounded-md p-2 text-ink hover:bg-canvas lg:hidden"
              onClick={() => setMobileNavOpen(true)}
            >
              <Menu size={20} aria-hidden />
            </button>
            <Breadcrumbs items={ALL_ITEMS} />
          </div>
          <div className="flex items-center gap-2">
            {user && <NotificationsMenu />}
            {user && <UserMenu />}
          </div>
        </header>
        {/* Seule zone de scroll vertical de l'application authentifiée : min-h-0 (même raison
            qu'au-dessus) + overflow-y-auto (absent avant ce correctif, donc <main> ne pouvait
            tout simplement pas défiler par lui-même). */}
        <main className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

/** Fil d'Ariane dérivé de la route active — jamais de libellé inventé pour un segment inconnu
 * (ex. un :id), affiché tel quel plutôt que deviné. */
function Breadcrumbs({ items }: { items: NavItem[] }) {
  const location = useLocation();
  const current = items.find((item) =>
    item.end
      ? location.pathname === item.to
      : location.pathname.startsWith(item.to),
  );
  const rest = location.pathname
    .split("/")
    .filter(Boolean)
    .slice(
      current && current.to !== "/"
        ? current.to.split("/").filter(Boolean).length
        : 0,
    );
  const CurrentIcon = current?.icon ?? LayoutDashboard;

  return (
    <div className="hidden items-center gap-1.5 text-sm text-muted sm:flex">
      <CurrentIcon size={16} aria-hidden />
      <span className="font-medium text-ink">
        {current?.label ?? "Tableau de bord"}
      </span>
      {rest.map((segment, i) => (
        <span key={i} className="flex items-center gap-1.5">
          <span aria-hidden>/</span>
          <span>{segment}</span>
        </span>
      ))}
    </div>
  );
}

/** "3" en dessous de 10, "9+"/"99+" au-delà — jamais un badge "0" (cahier des charges §12). */
function formatBadgeCount(count: number): string {
  if (count > 99) return "99+";
  if (count > 9) return "9+";
  return String(count);
}

/**
 * Centre de notifications (§6/Topbar) : cloche + badge de compteur nourris par de vraies
 * données backend (voir useNotifications/NotificationController), jamais un contenu simulé.
 * L'état "e-mail non vérifié" n'est volontairement pas dupliqué ici (ni ailleurs dans le
 * layout, voir §37 audit du 12/09) : ni bannière, ni notification persistante — le backend
 * (email_verified_at, vérification, renvoi) reste inchangé et pleinement fonctionnel.
 */
function NotificationsMenu() {
  const navigate = useNavigate();
  const {
    notifications,
    unreadCount,
    loading,
    error,
    refresh,
    markAsRead,
    markAllAsRead,
  } = useNotifications();
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;

    refresh();

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        setOpen(false);
        triggerRef.current?.focus();
      }
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  async function handleSelect(notification: AppNotification) {
    if (!notification.is_read) {
      await markAsRead(notification.id);
    }
    setOpen(false);
    if (notification.route) {
      navigate(notification.route);
    }
  }

  return (
    <div className="relative">
      <button
        ref={triggerRef}
        type="button"
        aria-label={
          unreadCount > 0
            ? `Notifications, ${unreadCount} non lues`
            : "Notifications"
        }
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
        className="relative rounded-md p-2 text-ink hover:bg-canvas"
      >
        <Bell size={20} aria-hidden />
        {unreadCount > 0 && (
          <span
            aria-hidden
            className="absolute -right-1 -top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold leading-none text-white"
          >
            {formatBadgeCount(unreadCount)}
          </span>
        )}
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
          <div
            role="menu"
            aria-label="Notifications"
            className="fixed inset-x-3 top-[4.5rem] z-20 overflow-hidden rounded-lg border border-line bg-surface shadow-md sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-[22rem]"
          >
            <div className="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
              <div className="min-w-0">
                <h3 className="text-sm font-semibold text-ink">
                  Notifications
                </h3>
                <p className="truncate text-xs text-muted">
                  {unreadCount > 0
                    ? `${unreadCount} non lue${unreadCount > 1 ? "s" : ""}`
                    : "Vous êtes à jour"}
                </p>
              </div>
              {unreadCount > 0 && (
                <button
                  type="button"
                  onClick={() => void markAllAsRead()}
                  className="flex-shrink-0 text-xs font-medium text-primary hover:underline"
                >
                  Tout marquer comme lu
                </button>
              )}
            </div>

            <div className="max-h-80 overflow-y-auto">
              {loading && notifications.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-muted">
                  Chargement des notifications…
                </p>
              ) : error ? (
                <div className="px-4 py-6 text-center">
                  <p className="text-sm text-muted">{error}</p>
                  <button
                    type="button"
                    onClick={refresh}
                    className="mt-2 text-sm font-medium text-primary hover:underline"
                  >
                    Réessayer
                  </button>
                </div>
              ) : notifications.length === 0 ? (
                <div className="px-4 py-8 text-center">
                  <Bell
                    size={22}
                    className="mx-auto mb-2 text-muted"
                    aria-hidden
                  />
                  <p className="text-sm font-medium text-ink">
                    Aucune notification
                  </p>
                  <p className="text-xs text-muted">Vous êtes à jour.</p>
                </div>
              ) : (
                <ul>
                  {notifications.map((notification) => {
                    const Icon = notificationIcon(notification.type);
                    return (
                      <li key={notification.id}>
                        <button
                          type="button"
                          role="menuitem"
                          onClick={() => void handleSelect(notification)}
                          className={[
                            "flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-canvas",
                            !notification.is_read ? "bg-primary-light" : "",
                          ].join(" ")}
                        >
                          <span className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-surface text-primary-dark ring-1 ring-inset ring-line">
                            <Icon size={16} aria-hidden />
                          </span>
                          <span className="min-w-0 flex-1">
                            <span className="flex items-center gap-1.5">
                              <span
                                className={`truncate text-sm text-ink ${!notification.is_read ? "font-semibold" : "font-medium"}`}
                              >
                                {notification.title}
                              </span>
                              {!notification.is_read && (
                                <span
                                  aria-hidden
                                  className="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary"
                                />
                              )}
                            </span>
                            {notification.message && (
                              <span className="mt-0.5 block truncate text-xs text-muted">
                                {notification.message}
                              </span>
                            )}
                            <span className="mt-1 block text-[11px] text-muted">
                              {formatRelativeTime(notification.created_at)}
                            </span>
                          </span>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>

            <div className="border-t border-line px-4 py-2.5 text-center">
              <NavLink
                to="/notifications"
                onClick={() => setOpen(false)}
                className="text-sm font-medium text-primary hover:underline"
              >
                Voir toutes les notifications
              </NavLink>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

/** Initiales dérivées du nom réel de l'utilisateur (ex. "Jean Rakoto" → "JR") — jamais de photo
 * de profil fictive : un simple avatar textuel tant qu'aucune fonctionnalité d'upload n'existe. */
function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/** Menu utilisateur (avatar/initiales + nom/rôle + accès Paramètres/Déconnexion) — remplace le
 * couple "nom affiché + bouton Déconnexion" toujours visible par un menu déroulant compact,
 * plus proche d'une topbar SaaS professionnelle (maquette de référence). */
function UserMenu() {
  const { user, logout } = useAuth();
  const [open, setOpen] = useState(false);
  if (!user) return null;

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-canvas"
        aria-label="Menu utilisateur"
      >
        <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary-light text-xs font-semibold text-primary-dark">
          {initials(user.name)}
        </span>
        <span className="hidden flex-col items-start text-left text-sm leading-tight sm:flex">
          <strong className="text-ink">{user.name}</strong>
          <span className="text-xs text-muted">
            {ROLE_LABELS[user.role] ?? user.role}
          </span>
        </span>
        <ChevronDown
          size={16}
          className="hidden text-muted sm:block"
          aria-hidden
        />
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
          <div className="absolute right-0 z-20 mt-2 w-56 rounded-lg border border-line bg-surface p-1.5 shadow-md">
            <div className="border-b border-line px-2.5 py-2 sm:hidden">
              <strong className="block text-sm text-ink">{user.name}</strong>
              <span className="text-xs text-muted">
                {ROLE_LABELS[user.role] ?? user.role}
              </span>
            </div>
            <NavLink
              to="/profile"
              onClick={() => setOpen(false)}
              className="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-ink hover:bg-canvas"
            >
              <Settings size={16} aria-hidden /> Paramètres
            </NavLink>
            <button
              type="button"
              onClick={() => void logout()}
              className="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm text-danger hover:bg-danger-bg"
            >
              <LogOut size={16} aria-hidden /> Déconnexion
            </button>
          </div>
        </>
      )}
    </div>
  );
}
