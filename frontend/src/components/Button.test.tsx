import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Button } from './Button'

describe('Button', () => {
  it('renders its children and forwards clicks', async () => {
    const onClick = vi.fn()
    render(<Button onClick={onClick}>Enregistrer</Button>)

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('shows a loading label and disables the button when loading', () => {
    render(<Button loading>Enregistrer</Button>)

    const button = screen.getByRole('button', { name: 'Chargement…' })
    expect(button).toBeDisabled()
  })

  it('stays disabled and inert when disabled is set, even without loading', async () => {
    const onClick = vi.fn()
    render(
      <Button disabled onClick={onClick}>
        Enregistrer
      </Button>,
    )

    const button = screen.getByRole('button', { name: 'Enregistrer' })
    expect(button).toBeDisabled()
    await userEvent.click(button)
    expect(onClick).not.toHaveBeenCalled()
  })
})
