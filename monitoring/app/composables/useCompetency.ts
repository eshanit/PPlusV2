import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import { COMPETENT_SCORE } from '~/data/evaluationItemData'

export type CompetencyStatus = 'fully_competent' | 'basic_competent' | 'in_progress'

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

function sortSessions(sessions: ISession[]): ISession[] {
  return [...sessions].sort((a, b) =>
    a.evalDate - b.evalDate
    || (a.roundOfDay ?? Number.POSITIVE_INFINITY) - (b.roundOfDay ?? Number.POSITIVE_INFINITY)
    || a.createdAt - b.createdAt,
  )
}

export interface LatestItemScore {
  score: number
  evalDate: number
  sessionId: string
}

/**
 * Each tool item's most recent recorded score across the whole journey:
 * the latest session that scored it, and within that session its latest
 * round. N/A never replaces an earlier score. Counselling items are ignored.
 */
export function getLatestItemScores(sessions: ISession[]): Map<string, LatestItemScore> {
  const latest = new Map<string, LatestItemScore>()
  for (const session of sortSessions(sessions)) {
    for (const round of getSessionRounds(session)) {
      for (const score of round.itemScores ?? []) {
        if (score.menteeScore === null) continue
        latest.set(score.itemSlug, { score: score.menteeScore, evalDate: session.evalDate, sessionId: session._id })
      }
    }
  }
  return latest
}

export interface ItemBelowThreshold {
  item: IEvalItem
  /** Most recent recorded score for the item in this journey, or undefined if never scored. */
  latestScore: number | undefined
}

export interface CompetencyBreakdown {
  status: CompetencyStatus
  /** Required (non-advanced) tool items whose latest score is below 4, or never scored. These block basic competency. */
  basicBelow: ItemBelowThreshold[]
  /** Advanced tool items whose latest score is below 4, or never scored. These block full competency only. */
  advancedBelow: ItemBelowThreshold[]
  /** Number of required (non-advanced) items in the tool. */
  requiredCount: number
  /** Required items whose latest score is 4 or 5. */
  requiredAchieved: number
}

/**
 * Competency status plus the items holding it back.
 *
 * Rule, matching the paper tool: an item counts as achieved once its most
 * recent score in the journey is 4 or 5. Mentors don't need to re-rate it in
 * later sessions, but a lower re-rating makes it not achieved again.
 * - basic_competent: every required (non-advanced) item in the tool is achieved.
 * - fully_competent: every item, including advanced ones, is achieved.
 */
export function getCompetencyBreakdown(
  sessions: ISession[],
  tool: ITool | null | undefined,
): CompetencyBreakdown {
  const requiredItems = tool?.items.filter(item => !item.isAdvanced) ?? []
  const empty: CompetencyBreakdown = {
    status: 'in_progress',
    basicBelow: requiredItems.map(item => ({ item, latestScore: undefined })),
    advancedBelow: tool?.items.filter(item => item.isAdvanced).map(item => ({ item, latestScore: undefined })) ?? [],
    requiredCount: requiredItems.length,
    requiredAchieved: 0,
  }
  if (!tool || sessions.length === 0) return empty

  const latest = getLatestItemScores(sessions)
  const below = tool.items
    .map(item => ({ item, latestScore: latest.get(item.slug)?.score }))
    .filter(entry => entry.latestScore === undefined || entry.latestScore < COMPETENT_SCORE)

  const basicBelow = below.filter(entry => !entry.item.isAdvanced)
  const advancedBelow = below.filter(entry => entry.item.isAdvanced)

  let status: CompetencyStatus = 'in_progress'
  if (requiredItems.length > 0 && basicBelow.length === 0) {
    status = advancedBelow.length === 0 ? 'fully_competent' : 'basic_competent'
  }

  return {
    status,
    basicBelow,
    advancedBelow,
    requiredCount: requiredItems.length,
    requiredAchieved: requiredItems.length - basicBelow.length,
  }
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
