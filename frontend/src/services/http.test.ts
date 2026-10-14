import { describe, expect, it } from 'vitest'
import { AxiosError } from 'axios'
import { toApiError } from './http'

function makeAxiosError(status: number | null, data?: unknown): AxiosError {
  const error = new AxiosError('Request failed')
  if (status !== null) {
    // @ts-expect-error — construction minimale suffisante pour axios.isAxiosError()/toApiError()
    error.response = { status, data, statusText: '', headers: {}, config: error.config }
  }
  return error
}

describe('toApiError', () => {
  it('extracts field errors and message on a 422 validation response', () => {
    const error = makeAxiosError(422, {
      message: 'The name field is required.',
      errors: { name: ['The name field is required.'] },
    })

    const result = toApiError(error)

    expect(result.status).toBe(422)
    expect(result.message).toBe('The name field is required.')
    expect(result.errors).toEqual({ name: ['The name field is required.'] })
  })

  it('falls back to the server message when there are no field errors', () => {
    const error = makeAxiosError(403, { message: 'This action is unauthorized.' })

    const result = toApiError(error)

    expect(result.status).toBe(403)
    expect(result.message).toBe('This action is unauthorized.')
    expect(result.errors).toBeUndefined()
  })

  it('reports a connection problem when there is no response at all (network error)', () => {
    const error = makeAxiosError(null)

    const result = toApiError(error)

    expect(result.status).toBeNull()
    expect(result.message).toMatch(/serveur/i)
  })

  it('extracts the structured AI-service error envelope (code, message, crop_key)', () => {
    const error = makeAxiosError(503, {
      success: false,
      error: {
        code: 'TRAINING_REQUIRED',
        message: 'Aucun modèle entraîné n\'est disponible pour la culture « patate douce ».',
        crop_key: 'patate_douce',
      },
    })

    const result = toApiError(error)

    expect(result.status).toBe(503)
    expect(result.code).toBe('TRAINING_REQUIRED')
    expect(result.cropKey).toBe('patate_douce')
    expect(result.message).toMatch(/patate douce/i)
  })

  it('produces a generic message for a status with no server message', () => {
    const error = makeAxiosError(500, {})

    const result = toApiError(error)

    expect(result.status).toBe(500)
    expect(result.message).toBe('Erreur inattendue (500).')
  })

  it('handles a completely unexpected (non-Axios) error without throwing', () => {
    const result = toApiError(new Error('boom'))

    expect(result.status).toBeNull()
    expect(result.message).toBe('Erreur inattendue.')
  })
})
