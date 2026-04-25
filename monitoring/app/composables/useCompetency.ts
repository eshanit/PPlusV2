import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'

export type CompetencyStatus = 'fully_competent' | 'basic_competent' | 'in_progress'

function buildLatestScores(sessions: ISession[]): Map<string, number> {
  const map = new Map<string, number>()
  const sorted = [...sessions].sort((a, b) => a.evalDate - b.evalDate || a.createdAt - b.createdAt)
  for (const session of sorted) {
    for (const score of session.itemScores ?? []) {
      if (score.menteeScore !== null) {
        map.set(score.itemSlug, score.menteeScore)
      }
    }
  }
  return map
}

// Pure function — safe to call inside computed()
export function getCompetencyStatus(
  sessions: ISession[],
  tool: ITool | null | undefined,
): CompetencyStatus {
  if (!tool || sessions.length === 0) return 'in_progress'

  const latest = buildLatestScores(sessions)

  const allCompetent = tool.items.every(item => {
    const s = latest.get(item.slug)
    return s !== undefined && s >= 4
  })
  if (allCompetent) return 'fully_competent'

  const basicItems = tool.items.filter(i => !i.isAdvanced)
  const basicCompetent =
    basicItems.length > 0 &&
    basicItems.every(item => {
      const s = latest.get(item.slug)
      return s !== undefined && s >= 4
    })
  if (basicCompetent) return 'basic_competent'

  return 'in_progress'
}

// Reactive composable for single-journey views
export function useCompetency(
  sessions: Ref<ISession[]>,
  tool: Ref<ITool | null | undefined>,
) {
  const status = computed(() => getCompetencyStatus(sessions.value, tool.value))
  const isBasicCompetent = computed(
    () => status.value === 'basic_competent' || status.value === 'fully_competent',
  )
  const isFullyCompetent = computed(() => status.value === 'fully_competent')

  return { status, isBasicCompetent, isFullyCompetent }
}
