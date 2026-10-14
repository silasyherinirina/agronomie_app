import { AxiosError } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { downloadReport } from './api'
import { http } from '../../services/http'

vi.mock('../../services/http', async () => {
  const actual = await vi.importActual<typeof import('../../services/http')>('../../services/http')
  return { ...actual, http: { get: vi.fn() } }
})

function makeBlobAxiosError(json: unknown, status = 422): AxiosError {
  const blob = new Blob([JSON.stringify(json)], { type: 'application/json' })
  const error = new AxiosError('Request failed')
  // @ts-expect-error — construction minimale suffisante pour axios.isAxiosError()
  error.response = { status, data: blob, statusText: '', headers: {}, config: error.config }
  return error
}

describe('downloadReport', () => {
  beforeEach(() => {
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:mock-url'), revokeObjectURL: vi.fn() })
  })

  it('triggers a browser download using the filename from Content-Disposition', async () => {
    const blob = new Blob(['col1;col2'], { type: 'text/csv' })
    vi.mocked(http.get).mockResolvedValue({
      data: blob,
      headers: { 'content-disposition': 'attachment; filename="ventes-20260911.csv"' },
    })

    const clickSpy = vi.fn()
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const originalCreateElement = document.createElement.bind(document)
    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      const el = originalCreateElement(tag)
      if (tag === 'a') el.click = clickSpy
      return el
    })

    await downloadReport('sales', 'csv', { crop_cycle_id: 1 })

    expect(http.get).toHaveBeenCalledWith('/reports/sales', {
      params: { format: 'csv', crop_cycle_id: 1 },
      responseType: 'blob',
    })
    expect(clickSpy).toHaveBeenCalled()
    expect(appendSpy).toHaveBeenCalled()
  })

  it('parses a JSON validation error hidden inside the blob error response', async () => {
    vi.mocked(http.get).mockRejectedValue(
      makeBlobAxiosError({ message: 'Format invalide.', errors: { format: ['Le champ format est invalide.'] } }),
    )

    await expect(downloadReport('sales', 'csv', {})).rejects.toMatchObject({
      status: 422,
      errors: { format: ['Le champ format est invalide.'] },
    })
  })
})
