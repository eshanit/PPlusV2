import type { SyncStatus } from './ISession'

export type GapDomain =
  | 'knowledge'
  | 'critical_reasoning'
  | 'clinical_skills'
  | 'communication'
  | 'attitude'

// Overall supervision level the evaluator recommends for this mentee+tool combination
export type SupervisionLevel = 'intensive_mentorship' | 'ongoing_mentorship' | 'independent_practice'

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
  resolvedAt?: number

  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
