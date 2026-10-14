import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { DataTable, type Column } from './DataTable'

interface Row {
  id: number
  name: string
}

const columns: Column<Row>[] = [
  { key: 'name', header: 'Nom', render: (row) => row.name },
]

describe('DataTable', () => {
  it('shows a spinner while loading, regardless of rows', () => {
    render(<DataTable columns={columns} rows={[{ id: 1, name: 'Riz' }]} rowKey={(r) => r.id} loading />)
    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText('Riz')).not.toBeInTheDocument()
  })

  it('shows the empty message when there are no rows', () => {
    render(<DataTable columns={columns} rows={[]} rowKey={(r) => r.id} emptyMessage="Rien à afficher." />)
    expect(screen.getByText('Rien à afficher.')).toBeInTheDocument()
  })

  it('renders one row per item using the column render function', () => {
    const rows: Row[] = [{ id: 1, name: 'Riz' }, { id: 2, name: 'Maïs' }]
    render(<DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />)

    expect(screen.getByText('Riz')).toBeInTheDocument()
    expect(screen.getByText('Maïs')).toBeInTheDocument()
    expect(screen.getAllByRole('row')).toHaveLength(3) // 1 en-tête + 2 lignes
  })
})
