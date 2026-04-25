<script setup lang="ts">
import { formatDistanceToNow } from 'date-fns'
import { useIntervalFn } from '@vueuse/core'
import { useDb } from '~/composables/useDb'
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useDistrictStore } from '~/stores/districtStore'
import { useSyncStore } from '~/stores/syncStore'

const router = useRouter()
const toast = useToast()
const { usersDb, sessionsDb, districtsDb } = useDb()

const userStore = useUserStore()
const sessionStore = useSessionStore()
const districtStore = useDistrictStore()
const syncStore = useSyncStore()

const syncing = ref({
  users: false,
  sessions: false,
  districts: false,
})

const lastSynced = ref({
  users: null as number | null,
  sessions: null as number | null,
  districts: null as number | null,
})

const status = ref({
  users: 'idle' as 'idle' | 'success' | 'error',
  sessions: 'idle' as 'idle' | 'success' | 'error',
  districts: 'idle' as 'idle' | 'success' | 'error',
})

const errorMsg = ref({
  users: '',
  sessions: '',
  districts: '',
})

async function syncUsers() {
  syncing.value.users = true
  status.value.users = 'idle'
  errorMsg.value.users = ''
  
  try {
    const config = useRuntimeConfig()
    const remoteUrl = `${(config.public.couchdbUrl as string).replace(/\/$/, '')}/penplus_users`
    await usersDb.replicate.from(remoteUrl)
    await userStore.loadUsers()
    lastSynced.value.users = Date.now()
    status.value.users = 'success'
    toast.add({ title: 'Users synced', color: 'success', icon: 'i-heroicons-check-circle' })
  } catch (err) {
    status.value.users = 'error'
    errorMsg.value.users = err instanceof Error ? err.message : String(err)
    toast.add({ title: 'Sync failed', description: errorMsg.value.users, color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    syncing.value.users = false
  }
}

async function syncSessions() {
  syncing.value.sessions = true
  status.value.sessions = 'idle'
  errorMsg.value.sessions = ''
  
  try {
    const config = useRuntimeConfig()
    const remoteUrl = `${(config.public.couchdbUrl as string).replace(/\/$/, '')}/penplus_sessions`
    await sessionsDb.replicate.from(remoteUrl)
    await sessionStore.loadAll()
    lastSynced.value.sessions = Date.now()
    status.value.sessions = 'success'
    toast.add({ title: 'Sessions synced', color: 'success', icon: 'i-heroicons-check-circle' })
  } catch (err) {
    status.value.sessions = 'error'
    errorMsg.value.sessions = err instanceof Error ? err.message : String(err)
    toast.add({ title: 'Sync failed', description: errorMsg.value.sessions, color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    syncing.value.sessions = false
  }
}

async function syncDistricts() {
  syncing.value.districts = true
  status.value.districts = 'idle'
  errorMsg.value.districts = ''
  
  try {
    const config = useRuntimeConfig()
    const remoteUrl = `${(config.public.couchdbUrl as string).replace(/\/$/, '')}/penplus_districts`
    await districtsDb.replicate.from(remoteUrl)
    await districtStore.loadAll()
    lastSynced.value.districts = Date.now()
    status.value.districts = 'success'
    toast.add({ title: 'Districts synced', color: 'success', icon: 'i-heroicons-check-circle' })
  } catch (err) {
    status.value.districts = 'error'
    errorMsg.value.districts = err instanceof Error ? err.message : String(err)
    toast.add({ title: 'Sync failed', description: errorMsg.value.districts, color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    syncing.value.districts = false
  }
}

const now = ref(Date.now())
useIntervalFn(() => { now.value = Date.now() }, 30_000)

function formatSyncTime(ts: number | null): string {
  if (!ts) return 'Never synced'
  void now.value
  return formatDistanceToNow(ts, { addSuffix: true })
}

async function doCleanup() {
  try {
    const { purgedSessions } = await syncStore.runCleanup()
    toast.add({
      title: 'Cleanup complete',
      description: purgedSessions > 0
        ? `Removed ${purgedSessions} old session${purgedSessions !== 1 ? 's' : ''} from closed journeys`
        : 'Nothing to remove — storage is already tidy',
      color: 'success',
      icon: 'i-heroicons-check-circle',
    })
  } catch (err) {
    toast.add({
      title: 'Cleanup failed',
      description: err instanceof Error ? err.message : String(err),
      color: 'error',
      icon: 'i-heroicons-x-circle',
    })
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center gap-3 mb-6">
      <UButton
        variant="ghost"
        color="neutral"
        icon="i-heroicons-arrow-left"
        @click="router.back()"
      />
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">Sync Data</h1>
    </div>

    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
      <div class="flex items-start gap-3">
        <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
        <div>
          <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
            Manual Sync
          </p>
          <p class="text-xs text-yellow-700 dark:text-yellow-400 mt-1">
            Use these buttons to manually sync data from CouchDB if automatic sync fails. This will pull the latest data from the server.
          </p>
        </div>
      </div>
    </div>

    <!-- Users Sync -->
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-medium text-gray-900 dark:text-white">Users</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ userStore.allUsers.length }} users loaded · Last synced: {{ formatSyncTime(lastSynced.users) }}
          </p>
          <p v-if="errorMsg.users" class="text-xs text-red-500 mt-1">{{ errorMsg.users }}</p>
        </div>
        <UButton
          color="primary"
          :loading="syncing.users"
          @click="syncUsers"
        >
          <UIcon name="i-heroicons-arrow-path" class="w-4 h-4 mr-2" />
          Sync Users
        </UButton>
      </div>
      <div v-if="status.users === 'success'" class="mt-2 text-xs text-green-600 dark:text-green-400">
        ✓ Synced successfully
      </div>
    </div>

    <!-- Sessions Sync -->
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-medium text-gray-900 dark:text-white">Sessions / Evaluations</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ sessionStore.sessions.length }} sessions loaded · Last synced: {{ formatSyncTime(lastSynced.sessions) }}
          </p>
          <p v-if="errorMsg.sessions" class="text-xs text-red-500 mt-1">{{ errorMsg.sessions }}</p>
        </div>
        <UButton
          color="primary"
          :loading="syncing.sessions"
          @click="syncSessions"
        >
          <UIcon name="i-heroicons-arrow-path" class="w-4 h-4 mr-2" />
          Sync Sessions
        </UButton>
      </div>
      <div v-if="status.sessions === 'success'" class="mt-2 text-xs text-green-600 dark:text-green-400">
        ✓ Synced successfully
      </div>
    </div>

    <!-- Districts Sync -->
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-medium text-gray-900 dark:text-white">Districts</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ districtStore.districts.length }} districts loaded · Last synced: {{ formatSyncTime(lastSynced.districts) }}
          </p>
          <p v-if="errorMsg.districts" class="text-xs text-red-500 mt-1">{{ errorMsg.districts }}</p>
        </div>
        <UButton
          color="primary"
          :loading="syncing.districts"
          @click="syncDistricts"
        >
          <UIcon name="i-heroicons-arrow-path" class="w-4 h-4 mr-2" />
          Sync Districts
        </UButton>
      </div>
      <div v-if="status.districts === 'success'" class="mt-2 text-xs text-green-600 dark:text-green-400">
        ✓ Synced successfully
      </div>
    </div>

    <!-- Sync All Button -->
    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
      <UButton
        color="success"
        block
        size="lg"
        :loading="syncing.users || syncing.sessions || syncing.districts"
        @click="async () => { await syncUsers(); await syncSessions(); await syncDistricts() }"
      >
        <UIcon name="i-heroicons-arrow-path" class="w-5 h-5 mr-2" />
        Sync All
      </UButton>
    </div>

    <!-- Storage Cleanup -->
    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-medium text-gray-900 dark:text-white">Storage Cleanup</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Removes old sessions from completed journeys · keeps latest session per journey
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
              Last run: {{ formatSyncTime(syncStore.lastCleanupAt) }}
              <span v-if="syncStore.lastCleanupAt && syncStore.lastCleanupPurged >= 0">
                · {{ syncStore.lastCleanupPurged }} removed
              </span>
            </p>
          </div>
          <UButton
            color="neutral"
            variant="soft"
            :loading="syncStore.cleanupRunning"
            @click="doCleanup"
          >
            <UIcon name="i-heroicons-trash" class="w-4 h-4 mr-2" />
            Clean Up
          </UButton>
        </div>
      </div>
    </div>
  </div>
</template>