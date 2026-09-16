import { defineStore } from 'pinia'
import { format } from 'date-fns'
import { useDb } from '~/composables/useDb'
import type { ISession } from '~/interfaces/ISession'

export interface PreviousScore {
  score: number | null
  date: number
  sessionNumber: number
}

export const useSessionStore = defineStore('sessions', () => {
  const { sessionsDb } = useDb()

  const sessions = ref<ISession[]>([])
  const loading = ref(false)

  async function loadAll() {
    loading.value = true
    try {
      const result = await sessionsDb.allDocs<ISession>({ include_docs: true })
      sessions.value = result.rows
        .map(r => r.doc!)
        .filter(d => d.type === 'session')
    } finally {
      loading.value = false
    }
  }

  async function save(session: ISession): Promise<ISession> {
    const now = Date.now()
    const doc: ISession = { ...session, updatedAt: now }

    if (!doc._id) {
      doc._id = `session::${doc.evaluationGroupId}::${now}`
      doc.createdAt = now
    }

    const response = await sessionsDb.put(doc)
    doc._rev = response.rev

    const idx = sessions.value.findIndex(s => s._id === doc._id)
    if (idx >= 0) {
      sessions.value[idx] = doc
    } else {
      sessions.value.push(doc)
    }

    return doc
  }

  async function remove(id: string) {
    const doc = await sessionsDb.get(id)
    await sessionsDb.remove(doc)
    sessions.value = sessions.value.filter(s => s._id !== id)
  }

  // Sessions for a specific mentee+tool journey, ordered by evalDate, then by
  // round within same-day ties (roundOfDay, since paper notes are sometimes
  // typed up later out of order), then createdAt as a final tiebreak.
  // The index within this array + 1 is the session number.
  function sessionsForGroup(evaluationGroupId: string): ISession[] {
    return sessions.value
      .filter(s => s.evaluationGroupId === evaluationGroupId)
      .sort((a, b) => a.evalDate - b.evalDate || roundSortKey(a) - roundSortKey(b) || a.createdAt - b.createdAt)
  }

  function sessionNumber(session: ISession): number {
    return sessionsForGroup(session.evaluationGroupId).findIndex(s => s._id === session._id) + 1
  }

  // Calendar-day key for grouping same-day sessions into "rounds".
  // evalDate is always written at a fixed time-of-day for the picked date, so a
  // plain formatted-date comparison is sufficient (see sessions/new.vue).
  function dayKey(evalDate: number): string {
    return format(new Date(evalDate), 'yyyy-MM-dd')
  }

  // Ordering key for same-day rounds: prefer the evaluator's explicit choice
  // (roundOfDay) since sessions are sometimes typed up later from paper notes,
  // out of chronological order. Sessions saved before that field existed sort
  // after any explicitly-numbered round, by createdAt among themselves.
  function roundSortKey(session: ISession): number {
    return session.roundOfDay ?? Number.POSITIVE_INFINITY
  }

  // Sessions sharing the same evaluationGroupId AND calendar day as `session`,
  // ordered by round.
  function sessionsForDay(evaluationGroupId: string, evalDate: number): ISession[] {
    const key = dayKey(evalDate)
    return sessionsForGroup(evaluationGroupId)
      .filter(s => dayKey(s.evalDate) === key)
      .sort((a, b) => roundSortKey(a) - roundSortKey(b) || a.createdAt - b.createdAt)
  }

  // Display round number: the evaluator's explicit choice, or (for older data
  // saved before that field existed) the entry's position among same-day sessions.
  function roundNumber(session: ISession): number {
    if (session.roundOfDay != null) return session.roundOfDay
    return sessionsForDay(session.evaluationGroupId, session.evalDate)
      .findIndex(s => s._id === session._id) + 1
  }

  function roundsForDay(evaluationGroupId: string, evalDate: number): number {
    return sessionsForDay(evaluationGroupId, evalDate).length
  }

  // Round numbers already recorded for this mentee+tool on this calendar day.
  function takenRoundsForDay(evaluationGroupId: string, evalDate: number): Set<number> {
    const taken = new Set<number>()
    sessionsForDay(evaluationGroupId, evalDate).forEach((s, i) => {
      taken.add(s.roundOfDay ?? i + 1)
    })
    return taken
  }

  // Smallest round number not yet recorded for this day — the sensible default
  // when starting a new session: it fills gaps left by out-of-order paper
  // transcription before it ever suggests a brand-new round.
  function nextOpenRound(evaluationGroupId: string, evalDate: number): number {
    const taken = takenRoundsForDay(evaluationGroupId, evalDate)
    let round = 1
    while (taken.has(round)) round++
    return round
  }

  // Get the latest score for a specific item from previous sessions
  function getLatestScore(evaluationGroupId: string, itemSlug: string): PreviousScore | null {
    const groupSessions = sessionsForGroup(evaluationGroupId)
    if (groupSessions.length === 0) return null

    // Go through sessions in reverse order (newest first)
    for (const session of [...groupSessions].reverse()) {
      // Check tool items
      const toolScore = session.itemScores?.find(s => s.itemSlug === itemSlug)
      if (toolScore && toolScore.menteeScore !== null) {
        return {
          score: toolScore.menteeScore,
          date: session.evalDate,
          sessionNumber: sessionNumber(session),
        }
      }
      // Check counselling items
      const counsScore = session.counsellingScores?.find(s => s.itemSlug === itemSlug)
      if (counsScore && counsScore.menteeScore !== null) {
        return {
          score: counsScore.menteeScore,
          date: session.evalDate,
          sessionNumber: sessionNumber(session),
        }
      }
    }
    return null
  }

  // Get total session count for a specific mentee+tool
  function getSessionCount(evaluationGroupId: string): number {
    return sessionsForGroup(evaluationGroupId).length
  }

  return {
    sessions,
    loading,
    loadAll,
    save,
    remove,
    sessionsForGroup,
    sessionNumber,
    dayKey,
    roundSortKey,
    sessionsForDay,
    roundNumber,
    roundsForDay,
    takenRoundsForDay,
    nextOpenRound,
    getLatestScore,
    getSessionCount,
  }
})
