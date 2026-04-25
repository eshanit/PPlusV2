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