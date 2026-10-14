import type { ReactNode } from 'react'
import { Spinner } from './Spinner'

export interface Column<T> {
  key: string
  header: string
  render: (row: T) => ReactNode
  align?: 'left' | 'right' | 'center'
}

interface DataTableProps<T> {
  columns: Column<T>[]
  rows: T[]
  rowKey: (row: T) => string | number
  loading?: boolean
  emptyMessage?: string
}

/** Tableau générique piloté par colonnes — partagé par toutes les pages de listing. */
export function DataTable<T>({ columns, rows, rowKey, loading, emptyMessage = 'Aucune donnée.' }: DataTableProps<T>) {
  if (loading) {
    return <Spinner />
  }

  if (rows.length === 0) {
    return <p className="px-4 py-10 text-center text-muted">{emptyMessage}</p>
  }

  return (
    <div className="overflow-x-auto rounded-md border border-line">
      <table className="w-full min-w-full border-collapse">
        <thead>
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                style={{ textAlign: col.align ?? 'left' }}
                className="whitespace-nowrap border-b border-line bg-canvas px-3.5 py-2.5 text-xs tracking-wide text-muted uppercase"
              >
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={rowKey(row)} className="[&:last-child>td]:border-b-0 hover:bg-canvas">
              {columns.map((col) => (
                <td
                  key={col.key}
                  style={{ textAlign: col.align ?? 'left' }}
                  className="border-b border-line px-3.5 py-2.5 align-middle"
                >
                  {col.render(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
