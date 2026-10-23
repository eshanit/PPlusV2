import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import { COMPETENT_SCORE } from '~/data/evaluationItemData'
import { getSessionRounds, sortSessions } from '~/composables/useCompetency'

/**
 * Competency coverage for one journey: which tool items have been evaluated,
 * and how long ago. Mirrors the reporting view v_item_coverage.
 *
 * Mentors evaluate only what they observe, so an item can go unevaluated. A
 * journey cannot reach competency until every required item's latest score is
 * 4 or 5, so a never-evaluated item blocks it. Coverage counts every
 * evaluator's sessions, so whoever picks up a mentee sees what is left.
 */

/** A pending item counts as "not evaluated recently" after this many sessions without a score. */
export const STALE_AFTER_SESSIONS = 2

export type CoverageStatus = 'achieved' | 'pending' | 'never' | 'only_na'

export interface ItemCoverage {
  item: IEvalItem
  status: CoverageStatus
  latestScore?: number
  lastEvaluatedDate?: number
  /** Sessions since the item was last scored (all sessions if never scored). */
  sessionsSince: number
  timesEvaluated: number
  /** Below 4 and not scored for STALE_AFTER_SESSIONS or more sessions. */
  stale: boolean
}

export interface JourneyCoverage {
  items: ItemCoverage[]
  /** Required items never evaluated, or only marked N/A. */
  neverRequired: ItemCoverage[]
  /** Required items below 4 that have not been evaluated recently. */
  staleRequired: ItemCoverage[]
  /** Advanced items never evaluated (not required for competency). */
  neverAdvanced: ItemCoverage[]
  totalSessions: number
  lastSession?: { date: number; evaluatorName: string; evaluatorId: string }
}

export function getJourneyCoverage(
  sessions: ISession[],
  tool: ITool | null | undefined,
  staleAfter = STALE_AFTER_SESSIONS,
): JourneyCoverage {
  const ordered = sortSessions(sessions)
  const total = ordered.length
  const latestSession = ordered[total - 1]
  const empty: JourneyCoverage = { items: [], neverRequired: [], staleRequired: [], neverAdvanced: [], totalSessions: total }
  if (!tool) return empty

  // Per item: latest non-null score and the session it came from, plus N/A count.
  const seen = new Map<string, { score?: number; date?: number; index?: number; times: number; na: number }>()
  ordered.forEach((session, index) => {
    for (const round of getSessionRounds(session)) {
      for (const s of round.itemScores ?? []) {
        const entry = seen.get(s.itemSlug) ?? { times: 0, na: 0 }
        if (s.menteeScore === null) {
          entry.na += 1
        } else {
          entry.times += 1
          entry.score = s.menteeScore
          entry.date = session.evalDate
          entry.index = index
        }
        seen.set(s.itemSlug, entry)
      }
    }
  })

  const items: ItemCoverage[] = tool.items.map((item) => {
    const entry = seen.get(item.slug)
    const sessionsSince = entry?.index === undefined ? total : total - 1 - entry.index
    let status: CoverageStatus
    if (entry?.score !== undefined) status = entry.score >= COMPETENT_SCORE ? 'achieved' : 'pending'
    else status = (entry?.na ?? 0) > 0 ? 'only_na' : 'never'
    return {
      item,
      status,
      latestScore: entry?.score,
      lastEvaluatedDate: entry?.date,
      sessionsSince,
      timesEvaluated: entry?.times ?? 0,
      stale: status === 'pending' && sessionsSince >= staleAfter,
    }
  })

  const isNever = (c: ItemCoverage) => c.status === 'never' || c.status === 'only_na'
  return {
    items,
    neverRequired: items.filter(c => !c.item.isAdvanced && isNever(c)),
    staleRequired: items.filter(c => !c.item.isAdvanced && c.stale),
    neverAdvanced: items.filter(c => c.item.isAdvanced && isNever(c)),
    totalSessions: total,
    lastSession: latestSession
      ? {
          date: latestSession.evalDate,
          evaluatorName: `${latestSession.evaluator.firstname} ${latestSession.evaluator.lastname}`.trim(),
          evaluatorId: latestSession.evaluator.id,
        }
      : undefined,
  }
}
