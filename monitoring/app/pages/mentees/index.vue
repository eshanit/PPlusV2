
<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import type { IUserRef } from '~/interfaces/IUserRef'

const userStore = useUserStore()
const sessionStore = useSessionStore()
const gapStore = useGapStore()

const search = ref('')

// All users except the current evaluator
const mentees = computed(() =>
  userStore.allUsers.filter(u => u.id !== userStore.currentUser?.id)
)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return mentees.value
  return mentees.value.filter(u =>
    u.firstname.toLowerCase().includes(q) ||
    u.lastname.toLowerCase().includes(q) ||
    u.username.toLowerCase().includes(q)
  )
})

function initials(user: IUserRef): string {
  return `${user.firstname[0] ?? ''}${user.lastname[0] ?? ''}`.toUpperCase()
}

function sessionCount(menteeId: string): number {
  return sessionStore.sessions.filter(s => s.mentee.id === menteeId).length
}

function openGaps(menteeId: string): number {
  return gapStore.gaps.filter(g => g.menteeId === menteeId && !g.resolvedAt).length
}

function summaryText(menteeId: string): string {
  const count = sessionCount(menteeId)
  if (count === 0) return 'No sessions yet'
  return `${count} session${count > 1 ? 's' : ''}`
}
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">Mentees</h1>
    </div>

    <!-- Search -->
    <UInput
      v-model="search"
      placeholder="Search by name..."
      icon="i-heroicons-magnifying-glass"
      size="lg"
    />

    <!-- Loading -->
    <div v-if="userStore.loadingUsers" class="py-12 text-center text-gray-400">
      <UIcon name="i-heroicons-arrow-path" class="size-8 animate-spin mx-auto mb-2" />
      <p class="text-sm">Loading…</p>
    </div>

    <!-- Empty search -->
    <div v-else-if="filtered.length === 0" class="py-10 text-center text-gray-400 text-sm">
      <UIcon name="i-heroicons-users" class="size-10 mx-auto mb-2 text-gray-300" />
      <p v-if="search">No mentee matches "{{ search }}"</p>
      <p v-else>No other clinicians found.</p>
    </div>

    <!-- Mentee list -->
    <ul v-else class="space-y-2">
      <li
        v-for="mentee in filtered"
        :key="mentee.id"
      >
        <NuxtLink
          :to="`/mentees/${mentee.id}`"
          class="flex items-center gap-3 bg-white dark:bg-gray-900 rounded-xl px-4 py-3.5 shadow-sm border border-gray-100 dark:border-gray-800 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
        >
          <!-- Avatar -->
          <div class="size-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm shrink-0">
            {{ initials(mentee) }}
          </div>

          <!-- Name & stats -->
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 dark:text-white truncate">
              {{ mentee.firstname }} {{ mentee.lastname }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              {{ summaryText(mentee.id) }}
            </p>
          </div>

          <!-- Indicators -->
          <div class="flex items-center gap-2 shrink-0">
            <UBadge
              v-if="openGaps(mentee.id) > 0"
              :label="`${openGaps(mentee.id)} gap${openGaps(mentee.id) > 1 ? 's' : ''}`"
              color="warning"
              variant="soft"
              size="xs"
            />
            <UIcon name="i-heroicons-chevron-right" class="size-4 text-gray-400" />
          </div>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
