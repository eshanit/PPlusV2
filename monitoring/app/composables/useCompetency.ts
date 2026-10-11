import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'

export type CompetencyStatus = 'fully_competent' | 'basic_competent' | 'in_progress'

type ScoredItem = { itemSlug: string; menteeScore: 1 | 2 | 3 | 4 | 5 | null }

function getSessionRounds(session: ISession) {
  const rounds = Array.isArray(session.rounds) && session.rounds.length > 0
    ? session.rounds
    : [{
        roundId: 'legacy-round',
        itemScores: session.itemScores ?? [],
        counsellingScores: session.counsellingScores ?? [],
        startedAt: session.createdAt ?? Date.now(),
      }]

  return rounds
}

function sessionPlannedItems(session: ISession): string[] {
  if (Array.isArray(session.selectedItemSlugs)) return session.selectedItemSlugs
  return [...new Set(getSessionRounds(session).flatMap(round =>
    (round.itemScores ?? []).map(score => score.itemSlug),
  ))]
}

function latestScoresForSession(session: ISession): Map<string, number> {
  const latest = new Map<string, number>()

  for (const round of getSessionRounds(session)) {
    for (const score of round.itemScores ?? []) {
      if (score.menteeScore !== null) latest.set(score.itemSlug, score.menteeScore)
    }
  }

  return latest
}

function sortSessions(sessions: ISession[]): ISession[] {
  return [...sessions].sort((a, b) =>
    a.evalDate - b.evalDate
    || (a.roundOfDay ?? Number.POSITIVE_INFINITY) - (b.roundOfDay ?? Number.POSITIVE_INFINITY)
    || a.createdAt - b.createdAt,
  )
}

// Pure function — safe to call inside computed()
export function getCompetencyStatus(
  sessions: ISession[],
  tool: ITool | null | undefined,
): CompetencyStatus {
  if (!tool || sessions.length === 0) return 'in_progress'

  const orderedSessions = sortSessions(sessions)
  const latestSession = orderedSessions[orderedSessions.length - 1]
  if (!latestSession) return 'in_progress'

  const planned = new Set(orderedSessions.flatMap(sessionPlannedItems))
  const plannedItems = tool.items.filter(item => planned.has(item.slug))
  if (plannedItems.length === 0) return 'in_progress'

  const latestScores = latestScoresForSession(latestSession)
  const itemsCompetent = (items: typeof plannedItems): boolean =>
    items.length > 0 && items.every(item => {
      const score = latestScores.get(item.slug)
      return score !== undefined && score >= 4
    })

  if (itemsCompetent(plannedItems)) return 'fully_competent'

  const basicItems = plannedItems.filter(item => !item.isAdvanced)
  if (itemsCompetent(basicItems)) return 'basic_competent'

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
