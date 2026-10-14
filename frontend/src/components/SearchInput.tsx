import { useEffect, useState } from 'react'
import { useDebouncedValue } from '../hooks/useDebouncedValue'

interface SearchInputProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
}

/** Champ de recherche texte débouncé (350ms) avant de déclencher l'appel API. */
export function SearchInput({ value, onChange, placeholder = 'Rechercher…' }: SearchInputProps) {
  const [local, setLocal] = useState(value)
  const debounced = useDebouncedValue(local)

  useEffect(() => {
    if (debounced !== value) {
      onChange(debounced)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debounced])

  return (
    <input
      type="search"
      className="min-w-55 rounded-md border border-line bg-surface px-3 py-2 outline-none focus:border-primary focus:ring-2 focus:ring-primary-light"
      value={local}
      placeholder={placeholder}
      onChange={(e) => setLocal(e.target.value)}
    />
  )
}
