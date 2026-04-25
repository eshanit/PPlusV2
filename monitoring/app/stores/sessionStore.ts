import { defineStore } from 'pinia'
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

  // Sessions for a specific mentee+tool journey, ordered by evalDate then createdAt.
  // The index within this array + 1 is the session number.
  function sessionsForGroup(evaluationGroupId: string): ISession[] {
    return sessions.value
      .filter(s => s.evaluationGroupId === evaluationGroupId)
      .sort((a, b) => a.evalDate - b.evalDate || a.createdAt - b.createdAt)
  }

  function sessionNumber(session: ISession): number {
    return sessionsForGroup(session.evaluationGroupId).findIndex(s => s._id === session._id) + 1
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
    getLatestScore,
    getSessionCount,
  }
})
