import { Component, type ErrorInfo, type ReactNode } from 'react'
import { AlertTriangle, RefreshCw } from 'lucide-react'
import { Button } from './Button'

interface MapErrorBoundaryProps {
  children: ReactNode
}

interface MapErrorBoundaryState {
  hasError: boolean
}

/**
 * Filet de sécurité SECONDAIRE pour toute zone contenant une carte Leaflet.
 *
 * Ceci ne remplace PAS la correction de la cause réelle du crash (voir DashboardPage.tsx —
 * la carte reste désormais toujours montée, ce qui évite la destruction de l'instance Leaflet
 * pendant qu'un état obsolète continue d'y faire référence). Ce composant existe uniquement
 * pour éviter qu'un incident Leaflet imprévu (ex. régression future, bug d'une version de la
 * librairie) ne fasse planter toute la page React — au lieu de ça, seule la zone carte affiche
 * un message honnête ("Impossible de charger la carte."), avec un bouton "Réessayer" qui
 * remonte le sous-arbre pour retenter une initialisation propre.
 */
export class MapErrorBoundary extends Component<MapErrorBoundaryProps, MapErrorBoundaryState> {
  state: MapErrorBoundaryState = { hasError: false }

  static getDerivedStateFromError(): MapErrorBoundaryState {
    return { hasError: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // On journalise l'erreur réelle en console — jamais masquée — pour garder la trace exacte
    // en cas de régression future, tout en évitant que React ne démonte toute la page.
    console.error('MapErrorBoundary: erreur Leaflet interceptée', error, info.componentStack)
  }

  private retry = () => {
    this.setState({ hasError: false })
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex h-[320px] flex-col items-center justify-center gap-3 rounded-lg border border-line bg-canvas text-center">
          <AlertTriangle size={28} className="text-danger" aria-hidden />
          <p className="text-sm font-medium text-ink">Impossible de charger la carte.</p>
          <Button variant="secondary" onClick={this.retry}>
            <RefreshCw size={16} aria-hidden /> Réessayer
          </Button>
        </div>
      )
    }

    return this.props.children
  }
}
