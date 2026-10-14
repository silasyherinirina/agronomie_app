import { afterEach } from 'vitest'
import { cleanup } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'

// `globals: true` n'est pas activé (on préfère importer explicitement describe/it/expect dans
// chaque test) : React Testing Library ne peut donc pas enregistrer son nettoyage automatique
// tout seul — on le fait explicitement pour éviter les DOM qui s'accumulent entre les tests.
afterEach(() => {
  cleanup()
})
