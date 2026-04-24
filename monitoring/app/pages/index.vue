
<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useSyncStore } from '~/stores/syncStore'
import type { MentorshipPhase } from '~/interfaces/ISession'

const userStore = useUserStore()
const sessionStore = useSessionStore()
const syncStore = useSyncStore()

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning,'
  if (h < 17) return 'Good afternoon,'
  return 'Good evening,'
})

const recentSessions = computed(() =>
  sessionStore.sessions
    .filter(s => s.evaluator.id === userStore.currentUser?.id)
    .sort((a, b) => b.evalDate - a.evalDate)
    .slice(0, 10)
)

function formatDate(ts: number): string {
  return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatPhase(phase: MentorshipPhase | null): string {
  const labels: Record<MentorshipPhase, string> = {
    initial_intensive: 'Intensive',
    ongoing: 'Ongoing',
    supervision: 'Supervision',
  }
  return phase ? labels[phase] : '—'
}

function phaseColor(phase: MentorshipPhase | null): 'info' | 'success' | 'warning' | 'neutral' {
  if (phase === 'initial_intensive') return 'info'
  if (phase === 'ongoing') return 'success'
  if (phase === 'supervision') return 'warning'
  return 'neutral'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Greeting -->
    <div>
      <p class="text-sm text-gray-500 dark:text-gray-400">{{ greeting }}</p>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5">
        {{ userStore.currentUserFullName }}
      </h1>
    </div>

    <!-- Quick actions -->
    <div class="grid grid-cols-2 gap-3">
      <UButton
        to="/mentees"
        color="primary"
        size="xl"
        class="flex-col h-28 rounded-2xl"
        icon="i-heroicons-plus-circle"
      >
        <span class="mt-1 text-sm font-semibold">New Session</span>
      </UButton>

      <UButton
        to="/mentees"
        color="neutral"
        variant="outline"
        size="xl"
        class="flex-col h-28 rounded-2xl"
        icon="i-heroicons-users"
      >
        <span class="mt-1 text-sm font-semibold">My Mentees</span>
      </UButton>
    </div>

    <!-- Recent sessions -->
    <div>
      <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
        Recent Sessions
      </h2>

      <div v-if="recentSessions.length === 0" class="text-center py-10 text-gray-400">
        <UIcon name="i-heroicons-clipboard-document-list" class="size-10 mx-auto mb-2 text-gray-300" />
        <p class="text-sm">No sessions recorded yet.</p>
        <p class="text-xs mt-1">Tap "New Session" to start.</p>
      </div>

      <ul v-else class="space-y-2">
        <li
          v-for="session in recentSessions"
          :key="session._id"
          class="bg-white dark:bg-gray-900 rounded-xl px-4 py-3 shadow-sm border border-gray-100 dark:border-gray-800"
        >
          <div class="flex items-center justify-between">
            <div class="min-w-0">
              <p class="font-medium text-gray-900 dark:text-white text-sm truncate">
                {{ session.mentee.firstname }} {{ session.mentee.lastname }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                {{ session.toolSlug }} · {{ formatDate(session.evalDate) }}
              </p>
            </div>
            <UBadge
              :label="formatPhase(session.phase)"
              :color="phaseColor(session.phase)"
              variant="soft"
              size="sm"
            />
          </div>
        </li>
      </ul>
    </div>

    <!-- Sync status -->
    <div class="flex items-center justify-center gap-2 text-xs text-gray-400 pt-2">
      <span
        class="size-2 rounded-full"
        :class="syncStore.isOnline ? 'bg-green-400' : 'bg-gray-300'"
      />
      <span>{{ syncStore.isOnline ? 'Syncing' : 'Offline' }}</span>
      <span v-if="syncStore.lastSyncedAt">
        · Last sync {{ formatDate(syncStore.lastSyncedAt) }}
      </span>
    </div>
  </div>
</template>
