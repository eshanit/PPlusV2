import type { SyncStatus } from './ISession'

export type GapDomain =
  | 'knowledge'
  | 'critical_reasoning'
  | 'clinical_skills'
  | 'communication'
  | 'attitude'

// Overall supervision level the evaluator recommends for this mentee+tool combination
export type SupervisionLevel = 'intensive_mentorship' | 'ongoing_mentorship' | 'independent_practice'

// open → in_progress ("being addressed") → resolved; a resolved gap can be reopened.
export type GapStatus = 'open' | 'in_progress' | 'resolved'

export interface IGapStatusChange {
  status: GapStatus
  // The day the change applies from: the resolved date for 'resolved'.
  date: number
  // When the change was recorded.
  changedAt: number
  by: string
  byName?: string
  note?: string
  targetDate?: number
}

export interface IGapEntry {
  _id: string
  _rev?: string
  type: 'gap'

  // Ties this gap to a mentee+tool combination, same key used in ISession
  evaluationGroupId: string

  menteeId: string
  evaluatorId: string
  toolSlug: string

  identifiedAt: number    // Unix timestamp (ms) when the gap was noted

  // Round-of-day of the most recent session in the journey at the time this
  // gap was logged. Gaps stay journey-scoped (no session _id link) — this is
  // just a lightweight annotation, so it's optional for gaps logged before
  // this field existed or for journeys with no sessions yet.
  sessionRound?: number

  // The session in which the gap was identified, when it was logged from the
  // session form. The gap still belongs to the journey (evaluationGroupId) and
  // is followed up there; this link lets the session and its review show the
  // gaps found that day. Absent for gaps logged from the journey page.
  sessionId?: string

  // The round of that session in which the gap was found: its roundId, and its
  // 1-based position among the session's rounds (synced as round_index).
  roundId?: string
  roundIndex?: number

  description: string     // Free-text description of the specific gap

  // Multiple domains can apply to a single gap
  domains: GapDomain[]

  // Will this gap be addressed during the current mentorship cycle?
  coveredInMentorship: boolean | null   // null = not yet decided

  // If not covered now, should this gap be handled later?
  coveringLater: boolean

  // Free-text: 'next session', 'within 2 weeks', '3 months', etc.
  timeline?: string

  // Recommended supervision level for this mentee on this tool after the assessment
  supervisionLevel?: SupervisionLevel

  // Closure fields — filled in once the gap is considered resolved
  resolutionNote?: string
  // The day the gap was resolved, which the mentor can set to an earlier day.
  resolvedAt?: number

  // Where the gap stands. Absent on gaps saved before statuses existed: those
  // are resolved when resolvedAt is set, otherwise open. Use gapStatus().
  status?: GapStatus
  // "Address by" date, set when the gap is being addressed. Overdue after it.
  targetDate?: number
  // Every status change, oldest first.
  statusHistory?: IGapStatusChange[]

  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
