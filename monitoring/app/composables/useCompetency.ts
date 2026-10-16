import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'
import type { IEvalItem } from '~/interfaces/IEvalItem'

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

export interface ItemBelowThreshold {
  item: IEvalItem
  /** Latest non-null score in the most recent session, or undefined if not scored there. */
  latestScore: number | undefined
}

export interface CompetencyBreakdown {
  status: CompetencyStatus
  /** Non-advanced planned items still below the competent score. These block basic competency. */
  basicBelow: ItemBelowThreshold[]
  /** Advanced planned items still below the competent score. These block full competency only. */
  advancedBelow: ItemBelowThreshold[]
  plannedCount: number
}

/**
 * Competency status plus the planned items holding it back.
 * Rule: every item planned anywhere in the journey must score 4 or 5 in the
 * most recent session. Advanced items are needed for full competency only.
 */
export function getCompetencyBreakdown(
  sessions: ISession[],
  tool: ITool | null | undefined,
): CompetencyBreakdown {
  const empty: CompetencyBreakdown = { status: 'in_progress', basicBelow: [], advancedBelow: [], plannedCount: 0 }
  if (!tool || sessions.length === 0) return empty

  const orderedSessions = sortSessions(sessions)
  const latestSession = orderedSessions[orderedSessions.length - 1]
  if (!latestSession) return empty

  const planned = new Set(orderedSessions.flatMap(sessionPlannedItems))
  const plannedItems = tool.items.filter(item => planned.has(item.slug))
  if (plannedItems.length === 0) return empty

  const latestScores = latestScoresForSession(latestSession)
  const below = plannedItems
    .map(item => ({ item, latestScore: latestScores.get(item.slug) }))
    .filter(entry => entry.latestScore === undefined || entry.latestScore < 4)

  const basicBelow = below.filter(entry => !entry.item.isAdvanced)
  const advancedBelow = below.filter(entry => entry.item.isAdvanced)
  const hasBasicItems = plannedItems.some(item => !item.isAdvanced)

  let status: CompetencyStatus = 'in_progress'
  if (below.length === 0) status = 'fully_competent'
  else if (hasBasicItems && basicBelow.length === 0) status = 'basic_competent'

  return { status, basicBelow, advancedBelow, plannedCount: plannedItems.length }
}

// Pure function — safe to call inside computed()
export function getCompetencyStatus(
  sessions: ISession[],
  tool: ITool | null | undefined,
): CompetencyStatus {
  return getCompetencyBreakdown(sessions, tool).status
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
