import { useState, type ReactNode } from 'react'

export interface TabItem {
  key: string
  label: string
  content: ReactNode
}

interface TabsProps {
  items: TabItem[]
  defaultKey?: string
}

/** Onglets simples pour les fiches détaillées (coopérative, agriculteur...). Le contenu de
 * chaque onglet n'est monté qu'une fois sélectionné (évite des appels API inutiles au chargement
 * de la fiche pour des onglets jamais consultés). */
export function Tabs({ items, defaultKey }: TabsProps) {
  const [active, setActive] = useState(defaultKey ?? items[0]?.key)
  const activeItem = items.find((item) => item.key === active) ?? items[0]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        {items.map((item) => (
          <button
            key={item.key}
            type="button"
            role="tab"
            aria-selected={item.key === active}
            onClick={() => setActive(item.key)}
            className={[
              'flex-shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              item.key === active
                ? 'border-primary text-primary-dark'
                : 'border-transparent text-muted hover:text-ink',
            ].join(' ')}
          >
            {item.label}
          </button>
        ))}
      </div>
      <div role="tabpanel">{activeItem?.content}</div>
    </div>
  )
}
