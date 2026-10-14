import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Badge } from './Badge'

// Les assertions ciblent l'attribut sémantique data-tone plutôt qu'un nom de classe CSS :
// le badge reste testable indépendamment de son système de style (Phase C — migration
// Tailwind), sans coupler le test à une implémentation visuelle qui peut encore changer.
describe('Badge', () => {
  it('infers a success tone for a known active-like status', () => {
    render(<Badge label="active" />)
    expect(screen.getByText('active')).toHaveAttribute('data-tone', 'success')
  })

  it('infers a danger tone for a failed status', () => {
    render(<Badge label="failed" />)
    expect(screen.getByText('failed')).toHaveAttribute('data-tone', 'danger')
  })

  it('falls back to neutral for an unrecognized label', () => {
    render(<Badge label="mystère" />)
    expect(screen.getByText('mystère')).toHaveAttribute('data-tone', 'neutral')
  })

  it('lets an explicit tone override the inferred one', () => {
    render(<Badge label="active" tone="warning" />)
    expect(screen.getByText('active')).toHaveAttribute('data-tone', 'warning')
  })
})
