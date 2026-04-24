import { defineStore } from 'pinia'
import PouchDB from 'pouchdb'
import { useDb } from '~/composables/useDb'
import { useSessionStore } from './sessionStore'
import { useGapStore } from './gapStore'
import { useUserStore } from './userStore'

export type SyncState = 'idle' | 'active' | 'paused' | 'error'

export const useSyncStore = defineStore('sync', () => {
  const { sessionsDb, gapsDb } = useDb()

  const sessionsState = ref<SyncState>('idle')
  const gapsState = ref<SyncState>('idle')
  const sessionsError = ref<string | null>(null)
  const gapsError = ref<string | null>(null)
  const lastSyncedAt = ref<number | null>(null)

  let sessionsSync: PouchDB.Replication.Sync<object> | null = null
  let gapsSync: PouchDB.Replication.Sync<object> | null = null

  function remoteUrl(dbName: string): string {
    const config = useRuntimeConfig()
    return `${(config.public.couchdbUrl as string).replace(/\/$/, '')}/${dbName}`
  }

  // One-shot pull of users from CouchDB
  function pullUsers() {
    const userStore = useUserStore()
    userStore.pullFromCouchDb(remoteUrl('penplus_users'))
  }

  function startSync() {
    const sessionStore = useSessionStore()
    const gapStore = useGapStore()

    sessionsSync = sessionsDb
      .sync(remoteUrl('penplus_sessions'), { live: true, retry: true })
      .on('active', () => { sessionsState.value = 'active'; sessionsError.value = null })
      .on('paused', () => { sessionsState.value = 'paused'; lastSyncedAt.value = Date.now() })
      .on('error', (err: unknown) => { sessionsState.value = 'error'; sessionsError.value = err instanceof Error ? err.message : String(err) })
      .on('change', () => sessionStore.loadAll())

    gapsSync = gapsDb
      .sync(remoteUrl('penplus_gaps'), { live: true, retry: true })
      .on('active', () => { gapsState.value = 'active'; gapsError.value = null })
      .on('paused', () => { gapsState.value = 'paused'; lastSyncedAt.value = Date.now() })
      .on('error', (err: unknown) => { gapsState.value = 'error'; gapsError.value = err instanceof Error ? err.message : String(err) })
      .on('change', () => gapStore.loadAll())
  }

  function stopSync() {
    sessionsSync?.cancel()
    gapsSync?.cancel()
    sessionsSync = null
    gapsSync = null
    sessionsState.value = 'idle'
    gapsState.value = 'idle'
  }

  const isOnline = computed(() =>
    sessionsState.value === 'active' || sessionsState.value === 'paused'
  )

  const hasError = computed(() =>
    sessionsState.value === 'error' || gapsState.value === 'error'
  )

  return {
    sessionsState,
    gapsState,
    sessionsError,
    gapsError,
    lastSyncedAt,
    isOnline,
    hasError,
    pullUsers,
    startSync,
    stopSync,
  }
})
