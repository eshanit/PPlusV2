import type { IItemScore } from './IItemScore'
import type { IUserRef } from './IUserRef'

export type MentorshipPhase = 'initial_intensive' | 'ongoing' | 'supervision'
export type SyncStatus = 'pending' | 'synced' | 'failed'

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

  // Scores for the items of the selected tool
  itemScores: IItemScore[]

  // DC1–DC9 counselling scores, always included regardless of tool
  counsellingScores: IItemScore[]

  // Explicitly selected by the evaluator at the time of recording
  phase: MentorshipPhase | null

  // Explicit round-of-day (1-based) chosen by the evaluator at entry time.
  // Sessions are sometimes recorded on paper first and typed up later, out of
  // chronological order, so entry order (createdAt) can't be trusted to reflect
  // the true same-day round order — this is the source of truth for it instead.
  // Optional only because sessions saved before this field existed lack it.
  roundOfDay?: number

  notes?: string

  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
