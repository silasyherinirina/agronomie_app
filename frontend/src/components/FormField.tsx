import type { ReactNode } from 'react'
// Conserve la classe `form-field` (voir globals.css) : le sélecteur `.form-field input /
// select / textarea` stylise le champ enfant dans TOUTES les pages du projet. La retirer
// casserait l'apparence de chaque formulaire tant que ces champs n'auront pas été migrés
// individuellement vers Tailwind (hors périmètre de cette phase — migration progressive).
import './FormField.css'

interface FormFieldProps {
  label: string
  htmlFor: string
  errors?: string[]
  required?: boolean
  hint?: string
  children: ReactNode
}

/** Enveloppe label + champ + erreurs de validation (422) pour un champ de formulaire. */
export function FormField({ label, htmlFor, errors, required, hint, children }: FormFieldProps) {
  return (
    <div className="form-field flex flex-col gap-1.5">
      <label htmlFor={htmlFor} className="text-sm font-medium text-ink">
        {label}
        {required && <span className="text-danger"> *</span>}
      </label>
      {children}
      {hint && !errors?.length && <p className="m-0 text-xs text-muted">{hint}</p>}
      {errors?.map((msg) => (
        <p className="m-0 text-xs text-danger" key={msg}>
          {msg}
        </p>
      ))}
    </div>
  )
}
