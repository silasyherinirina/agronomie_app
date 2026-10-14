import { lazy, Suspense, type ReactNode } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './hooks/useAuth'
import { NotificationProvider } from './hooks/useNotifications'
import { ProtectedRoute } from './components/ProtectedRoute'
import { Spinner } from './components/Spinner'
import { AppLayout } from './layouts/AppLayout'
import { HomePage } from './pages/HomePage'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import { ForgotPasswordPage } from './pages/ForgotPasswordPage'
import { ResetPasswordPage } from './pages/ResetPasswordPage'
import { EmailVerifiedPage } from './pages/EmailVerifiedPage'
import { ProfilePage } from './pages/ProfilePage'
import { CooperativesListPage } from './features/cooperatives/CooperativesListPage'
import { CooperativeFormPage } from './features/cooperatives/CooperativeFormPage'
import { FarmersListPage } from './features/farmers/FarmersListPage'
import { FarmerFormPage } from './features/farmers/FarmerFormPage'
import { FarmerDetailPage } from './features/farmers/FarmerDetailPage'
import { PlotsListPage } from './features/plots/PlotsListPage'
import { PlotFormPage } from './features/plots/PlotFormPage'
import { CropsPage } from './features/crops/CropsPage'
import { CampaignsListPage } from './features/campaigns/CampaignsListPage'
import { CampaignFormPage } from './features/campaigns/CampaignFormPage'
import { CropCyclesListPage } from './features/cropCycles/CropCyclesListPage'
import { CropCycleFormPage } from './features/cropCycles/CropCycleFormPage'
import { HarvestsListPage } from './features/harvests/HarvestsListPage'
import { HarvestFormPage } from './features/harvests/HarvestFormPage'
import { InputsPage } from './features/inputs/InputsPage'
import { InputUsagesListPage } from './features/inputs/InputUsagesListPage'
import { InputUsageFormPage } from './features/inputs/InputUsageFormPage'
import { PredictionsPage } from './features/predictions/PredictionsPage'
import { AiAboutPage } from './features/predictions/AiAboutPage'
import { WeatherPage } from './features/weather/WeatherPage'
import { SalesListPage } from './features/sales/SalesListPage'
import { SaleFormPage } from './features/sales/SaleFormPage'
import { ReportsPage } from './features/reports/ReportsPage'

// Chargées à la demande (Phase 13 — performance) : Leaflet et Recharts représentent à eux
// seuls la majorité du bundle JS (~900 Ko avant ce découpage). Un utilisateur qui ne consulte
// jamais la carte ou le tableau de bord n'a plus à les télécharger dès le chargement initial.
const DashboardPage = lazy(() => import('./features/dashboard/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const MapPage = lazy(() => import('./features/map/MapPage').then((m) => ({ default: m.MapPage })))
// La fiche coopérative embarque désormais un onglet Carte (Leaflet) : chargée à la demande
// pour la même raison que Dashboard/Map ci-dessus.
const CooperativeDetailPage = lazy(() =>
  import('./features/cooperatives/CooperativeDetailPage').then((m) => ({ default: m.CooperativeDetailPage })),
)
const NotificationsPage = lazy(() =>
  import('./features/notifications/NotificationsPage').then((m) => ({ default: m.NotificationsPage })),
)

function LazyPage({ children }: { children: ReactNode }) {
  return <Suspense fallback={<Spinner />}>{children}</Suspense>
}

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <NotificationProvider>
          <Routes>
            {/* Page publique : ne redirige plus automatiquement vers /login (audit UI/UX). */}
            <Route path="/" element={<HomePage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />
            <Route path="/email-verified" element={<EmailVerifiedPage />} />

            {/* Route "pathless" (pas de `path`) : sert uniquement de layout partagé, sans
                consommer de segment d'URL — les routes filles ci-dessous gardent exactement
                leurs chemins absolus (`/cooperatives`, `/plots`...), aucun lien existant à
                mettre à jour ailleurs dans l'application. */}
            <Route
              element={
                <ProtectedRoute>
                  <AppLayout />
                </ProtectedRoute>
              }
            >
              <Route
                path="dashboard"
                element={
                  <LazyPage>
                    <DashboardPage />
                  </LazyPage>
                }
              />

              <Route path="cooperatives" element={<CooperativesListPage />} />
              <Route path="cooperatives/new" element={<CooperativeFormPage />} />
              <Route
                path="cooperatives/:id"
                element={
                  <LazyPage>
                    <CooperativeDetailPage />
                  </LazyPage>
                }
              />
              <Route path="cooperatives/:id/edit" element={<CooperativeFormPage />} />

              <Route path="farmers" element={<FarmersListPage />} />
              <Route path="farmers/new" element={<FarmerFormPage />} />
              <Route path="farmers/:id" element={<FarmerDetailPage />} />
              <Route path="farmers/:id/edit" element={<FarmerFormPage />} />

              <Route path="plots" element={<PlotsListPage />} />
              <Route path="plots/new" element={<PlotFormPage />} />
              <Route path="plots/:id/edit" element={<PlotFormPage />} />

              <Route
                path="map"
                element={
                  <LazyPage>
                    <MapPage />
                  </LazyPage>
                }
              />

              <Route path="crops" element={<CropsPage />} />

              <Route path="campaigns" element={<CampaignsListPage />} />
              <Route path="campaigns/new" element={<CampaignFormPage />} />
              <Route path="campaigns/:id/edit" element={<CampaignFormPage />} />

              <Route path="crop-cycles" element={<CropCyclesListPage />} />
              <Route path="crop-cycles/new" element={<CropCycleFormPage />} />
              <Route path="crop-cycles/:id/edit" element={<CropCycleFormPage />} />

              <Route path="harvests" element={<HarvestsListPage />} />
              <Route path="harvests/new" element={<HarvestFormPage />} />
              <Route path="harvests/:id/edit" element={<HarvestFormPage />} />

              <Route path="sales" element={<SalesListPage />} />
              <Route path="sales/new" element={<SaleFormPage />} />
              <Route path="sales/:id/edit" element={<SaleFormPage />} />

              <Route path="inputs" element={<InputsPage />} />
              <Route path="inputs/usages" element={<InputUsagesListPage />} />
              <Route path="inputs/usages/new" element={<InputUsageFormPage />} />
              <Route path="inputs/usages/:id/edit" element={<InputUsageFormPage />} />

              <Route path="predictions" element={<PredictionsPage />} />
              <Route path="predictions/about" element={<AiAboutPage />} />
              <Route path="weather" element={<WeatherPage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="profile" element={<ProfilePage />} />

              <Route
                path="notifications"
                element={
                  <LazyPage>
                    <NotificationsPage />
                  </LazyPage>
                }
              />

              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Route>
          </Routes>
        </NotificationProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
