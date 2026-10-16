interface ItemScore {
  itemSlug: string
  menteeScore: number | null
  notes?: string
}

export function calculateScoreCounts(items: ItemScore[]): Record<string, number> {
  const counts: Record<string, number> = { '1': 0, '2': 0, '3': 0, '4': 0, '5': 0, 'N/A': 0 }
  items.forEach(s => {
    const scoreKey = s.menteeScore?.toString() ?? 'N/A'
    if (scoreKey in counts) {
      counts[scoreKey] = (counts[scoreKey] ?? 0) + 1
    }
  })
  return counts
}

export function calculateAverage(items: ItemScore[]): string {
  const scored = items.filter(s => s.menteeScore !== null)
  if (scored.length === 0) return '0'
  const sum = scored.reduce((acc, s) => acc + (s.menteeScore ?? 0), 0)
  return (sum / scored.length).toFixed(1)
}

export function formatDate(ts: number | null | undefined): string {
  if (!ts) return 'N/A'
  return new Date(ts).toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}
export type ScoreChange = 'improved' | 'regressed' | 'unchanged'

export interface ScoreChangeDisplay {
  icon: string
  label: string
  class: string
}

/** Compare a score with the previous one. N/A or missing on either side gives no change. */
export function getScoreChange(
  current: number | null | undefined,
  previous: number | null | undefined,
): ScoreChange | undefined {
  if (current === null || current === undefined || previous === null || previous === undefined) return undefined
  if (current > previous) return 'improved'
  if (current < previous) return 'regressed'
  return 'unchanged'
}

const SCORE_CHANGE_DISPLAY: Record<ScoreChange, ScoreChangeDisplay> = {
  improved: { icon: 'i-heroicons-arrow-trending-up', label: 'Improved', class: 'text-green-700 dark:text-green-400' },
  regressed: { icon: 'i-heroicons-arrow-trending-down', label: 'Regressed', class: 'text-red-700 dark:text-red-400' },
  unchanged: { icon: 'i-heroicons-minus', label: 'No change', class: 'text-gray-500 dark:text-gray-400' },
}

export function getScoreChangeDisplay(
  current: number | null | undefined,
  previous: number | null | undefined,
): ScoreChangeDisplay | undefined {
  const change = getScoreChange(current, previous)
  return change ? SCORE_CHANGE_DISPLAY[change] : undefined
}

/** Average as a number, or null when nothing was scored. */
export function averageValue(items: ItemScore[]): number | null {
  const scored = items.filter(s => s.menteeScore !== null)
  if (scored.length === 0) return null
  return scored.reduce((acc, s) => acc + (s.menteeScore ?? 0), 0) / scored.length
}

export interface EchoAccuracy {
  performed: number
  correct: number
  /** Whole-number percentage, or null when no echocardiograms were performed. */
  percent: number | null
  /** e.g. "5 of 6 correct (83%)" */
  text: string
}

/** Diagnostic accuracy for supervised echocardiograms, or null if either count is missing. */
export function echoAccuracy(
  performed: number | null | undefined,
  correct: number | null | undefined,
): EchoAccuracy | null {
  if (performed === null || performed === undefined || correct === null || correct === undefined) return null
  const percent = performed > 0 ? Math.round((correct / performed) * 100) : null
  const text = performed > 0
    ? `${correct} of ${performed} correct (${percent}%)`
    : 'None performed'
  return { performed, correct, percent, text }
}
