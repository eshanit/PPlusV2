import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { useDistrictStore } from '~/stores/districtStore'
import { useSyncStore } from '~/stores/syncStore'

export default defineNuxtPlugin(async () => {
  const userStore = useUserStore()
  const sessionStore = useSessionStore()
  const gapStore = useGapStore()
  const districtStore = useDistrictStore()
  const syncStore = useSyncStore()

  userStore.loadFromStorage()

  // Load whatever is already in local PouchDB (instant, no network needed)
  await Promise.all([
    userStore.loadUsers(),
    sessionStore.loadAll(),
    gapStore.loadAll(),
    districtStore.loadAll(),
  ])

  // Start live sync for sessions, gaps, users, and districts.
  syncStore.startSync()
})
