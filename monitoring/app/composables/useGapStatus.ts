import { startOfDay } from 'date-fns'
import type { GapStatus, IGapEntry, IGapStatusChange } from '~/interfaces/IGapEntry'

/**
 * Gap statuses: open → being addressed → resolved, and a resolved gap can be
 * reopened. Gaps saved before statuses existed have no `status`: they are
 * resolved when `resolvedAt` is set, otherwise open.
 */

export const GAP_STATUS_LABELS: Record<GapStatus, string> = {
  open: 'Open',
  in_progress: 'Being addressed',
  resolved: 'Resolved',
}

export function gapStatus(gap: IGapEntry): GapStatus {
  if (gap.resolvedAt) return 'resolved'
  return gap.status === 'in_progress' ? 'in_progress' : 'open'
}

/** Not resolved, and its "address by" date is before today. */
export function isGapOverdue(gap: IGapEntry, now = Date.now()): boolean {
  return gapStatus(gap) !== 'resolved' && !!gap.targetDate && gap.targetDate < startOfDay(now).getTime()
}

export interface GapStatusChange {
  to: GapStatus
  /** The resolved date, for 'resolved'. Defaults to now. */
  date?: number
  /** The "address by" date, for 'in_progress'. */
  targetDate?: number
  note?: string
  by: string
  byName?: string
}

/** The gap after a status change, with the change added to its history. */
export function withGapStatus(gap: IGapEntry, change: GapStatusChange): IGapEntry {
  const now = Date.now()
  const note = change.note?.trim() || undefined
  const date = change.date ?? now
  const entry: IGapStatusChange = {
    status: change.to,
    date,
    changedAt: now,
    by: change.by,
    ...(change.byName ? { byName: change.byName } : {}),
    ...(note ? { note } : {}),
    ...(change.to === 'in_progress' && change.targetDate ? { targetDate: change.targetDate } : {}),
  }
  const history = [...(gap.statusHistory ?? []), entry]

  if (change.to === 'resolved') {
    return { ...gap, status: 'resolved', resolvedAt: date, resolutionNote: note, statusHistory: history, syncStatus: 'pending' }
  }

  // Open or being addressed: clear the closure fields. Earlier resolutions stay in the history.
  const { resolvedAt: _resolvedAt, resolutionNote: _note, targetDate: _target, ...rest } = gap
  return {
    ...rest,
    status: change.to,
    ...(change.to === 'in_progress' && change.targetDate ? { targetDate: change.targetDate } : {}),
    statusHistory: history,
    syncStatus: 'pending',
  }
}
