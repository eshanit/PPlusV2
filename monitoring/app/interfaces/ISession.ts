import type { IItemScore } from './IItemScore'
import type { IUserRef } from './IUserRef'

export type MentorshipPhase = 'initial_intensive' | 'ongoing' | 'supervision'
export type SyncStatus = 'pending' | 'synced' | 'failed'

export interface IRound {
  roundId: string
  label?: string
  itemScores: IItemScore[]
  counsellingScores: IItemScore[]
  // Competencies chosen for this round. Rounds in one session can evaluate
  // different items from the same tool. Absent on older sessions.
  selectedItemSlugs?: string[]
  selectedCounsellingSlugs?: string[]
  notes?: string
  startedAt: number
  completedAt?: number
}

export interface ISession {
  _id: string
  _rev?: string

  // Discriminator — lets PouchDB allDocs queries filter by document type cheaply
  type: 'session'

  // Links all sessions for the same mentee+tool together.
  // Format: `${mentee.id}::${toolSlug}`
  evaluationGroupId: string

  mentee: IUserRef
  evaluator: IUserRef

  toolSlug: string

  // sessionNumber is NOT stored — computed dynamically from all sessions sharing
  // the same evaluationGroupId, ordered by evalDate then createdAt.
  evalDate: number        // Unix timestamp (ms)

  facilityId: string
  districtId: string

  // Legacy flat score layout retained for compatibility with older docs and
  // existing session forms. Newer sessions should use the round-based layout.
  itemScores: IItemScore[]
  counsellingScores: IItemScore[]

  // Round-based layout for same-day observation passes. The legacy flat arrays
  // are still preserved so older code can keep working while the new UX is
  // phased in.
  rounds?: IRound[]
  selectedItemSlugs?: string[]
  selectedCounsellingSlugs?: string[]

  // Echo-specific session activity counts from the paper tool's two "#" rows.
  echocardiogramsPerformed?: number
  echocardiogramsCorrectDiagnosis?: number

  // Explicitly selected by the evaluator at the time of recording
  phase: MentorshipPhase | null

  // Explicit round-of-day (1-based) chosen by the evaluator at entry time.
  // Sessions are sometimes recorded on paper first and typed up later, out of
  // chronological order, so entry order (createdAt) can't be trusted to reflect
  // the true same-day round order — this is the source of truth for it instead.
  // Optional only because sessions saved before this field existed lack it.
  roundOfDay?: number

  // Set only on a session recorded after the mentor reopened a journey that had
  // already reached competency (e.g. a score of 4/5 was given in error). Holds
  // the mentor's reason. Reporting treats competency reached before the latest
  // reopened session as void.
  reopenReason?: string

  notes?: string

  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
