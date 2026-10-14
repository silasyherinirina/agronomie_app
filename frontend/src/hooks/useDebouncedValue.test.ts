import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useDebouncedValue } from './useDebouncedValue'

describe('useDebouncedValue', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('keeps the initial value immediately', () => {
    const { result } = renderHook(() => useDebouncedValue('a', 300))
    expect(result.current).toBe('a')
  })

  it('only updates after the delay has elapsed, and only to the latest value', () => {
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 300), {
      initialProps: { value: 'a' },
    })

    rerender({ value: 'ab' })
    rerender({ value: 'abc' })

    // Avant l'expiration du délai : toujours la valeur initiale.
    act(() => {
      vi.advanceTimersByTime(299)
    })
    expect(result.current).toBe('a')

    // Après expiration : la dernière valeur (pas une valeur intermédiaire).
    act(() => {
      vi.advanceTimersByTime(1)
    })
    expect(result.current).toBe('abc')
  })
})
