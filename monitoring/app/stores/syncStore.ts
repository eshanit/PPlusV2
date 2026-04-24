import { defineStore } from 'pinia'
import PouchDB from 'pouchdb'
import { useDb } from '~/composables/useDb'
import { useSessionStore } from './sessionStore'
import { useGapStore } from './gapStore'
import { useUserStore } from './userStore'
import { useDistrictStore } from './districtStore'

export type SyncState = 'idle' | 'active' | 'paused' | 'error'

export const useSyncStore = defineStore('sync', () => {
  const { sessionsDb, gapsDb, usersDb, districtsDb } = useDb()

  const sessionsState = ref<SyncState>('idle')
  const gapsState = ref<SyncState>('idle')
  const usersState = ref<SyncState>('idle')
  const districtsState = ref<SyncState>('idle')
  const sessionsError = ref<string | null>(null)
  const gapsError = ref<string | null>(null)
  const usersError = ref<string | null>(null)
  const districtsError = ref<string | null>(null)
  const lastSyncedAt = ref<number | null>(null)

  let sessionsSync: PouchDB.Replication.Sync<object> | null = null
  let gapsSync: PouchDB.Replication.Sync<object> | null = null
  let usersSync: PouchDB.Replication.Sync<object> | null = null
  let districtsSync: PouchDB.Replication.Sync<object> | null = null

  function remoteUrl(dbName: string): string {
    const config = useRuntimeConfig()
    return `${(config.public.couchdbUrl as string).replace(/\/$/, '')}/${dbName}`
  }

  // One-shot pull of users and districts from CouchDB
  async function pullAll() {
    const userStore = useUserStore()
    const districtStore = useDistrictStore()

    try {
      console.log('Pulling users from:', remoteUrl('penplus_users'))
      await usersDb.replicate.from(remoteUrl('penplus_users'), { batch_size: 200 })
      await userStore.loadUsers()
      console.log('Users loaded:', userStore.allUsers.length)
    } catch (err) {
      console.error('Failed to pull users:', err)
      throw new Error(`Failed to sync users: ${err instanceof Error ? err.message : String(err)}`)
    }

    try {
      console.log('Pulling districts from:', remoteUrl('penplus_districts'))
      await districtsDb.replicate.from(remoteUrl('penplus_districts'), { batch_size: 200 })
      await districtStore.loadAll()
      console.log('Districts loaded:', districtStore.districts.length)
      
      if (districtStore.districts.length === 0) {
        console.warn('No districts found. Check if penplus_districts database exists on CouchDB and contains documents.')
      }
    } catch (err) {
      console.error('Failed to pull districts:', err)
      throw new Error(`Failed to sync districts: ${err instanceof Error ? err.message : String(err)}`)
    }
  }

  function pullUsers() {
    const userStore = useUserStore()
    userStore.pullFromCouchDb(remoteUrl('penplus_users'))
  }

  function startSync() {
    const sessionStore = useSessionStore()
    const gapStore = useGapStore()
    const userStore = useUserStore()
    const districtStore = useDistrictStore()

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

    usersSync = usersDb
      .sync(remoteUrl('penplus_users'), { live: true, retry: true })
      .on('active', () => { usersState.value = 'active'; usersError.value = null })
      .on('paused', () => { usersState.value = 'paused'; lastSyncedAt.value = Date.now() })
      .on('error', (err: unknown) => { usersState.value = 'error'; usersError.value = err instanceof Error ? err.message : String(err) })
      .on('change', () => userStore.loadUsers())

    districtsSync = districtsDb
      .sync(remoteUrl('penplus_districts'), { live: true, retry: true })
      .on('active', () => { districtsState.value = 'active'; districtsError.value = null })
      .on('paused', () => { districtsState.value = 'paused'; lastSyncedAt.value = Date.now() })
      .on('error', (err: unknown) => { districtsState.value = 'error'; districtsError.value = err instanceof Error ? err.message : String(err) })
      .on('change', () => districtStore.loadAll())
  }

  function stopSync() {
    sessionsSync?.cancel()
    gapsSync?.cancel()
    usersSync?.cancel()
    districtsSync?.cancel()
    sessionsSync = null
    gapsSync = null
    usersSync = null
    districtsSync = null
    sessionsState.value = 'idle'
    gapsState.value = 'idle'
    usersState.value = 'idle'
    districtsState.value = 'idle'
  }

  const isOnline = computed(() =>
    sessionsState.value === 'active' ||
    sessionsState.value === 'paused' ||
    usersState.value === 'active' ||
    usersState.value === 'paused' ||
    districtsState.value === 'active' ||
    districtsState.value === 'paused'
  )

  const hasError = computed(() =>
    sessionsState.value === 'error' ||
    gapsState.value === 'error' ||
    usersState.value === 'error' ||
    districtsState.value === 'error'
  )

  return {
    sessionsState,
    gapsState,
    usersState,
    districtsState,
    sessionsError,
    gapsError,
    usersError,
    districtsError,
    lastSyncedAt,
    isOnline,
    hasError,
    pullAll,
    pullUsers,
    startSync,
    stopSync,
  }
})
