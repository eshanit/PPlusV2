<template>
  <div class="flex flex-col min-h-screen">
    <!-- Header -->
    <div class="bg-primary-600 dark:bg-primary-800 text-white px-6 pt-12 pb-8">
      <div class="max-w-md mx-auto">
        <div class="flex items-center gap-3 mb-4">
          <UIcon name="i-heroicons-heart" class="size-7" />
          <span class="text-xl font-bold tracking-wide">PenPlus NCD</span>
        </div>
        <h1 class="text-2xl font-semibold">Who are you?</h1>
        <p class="mt-1 text-primary-100 text-sm">Select your name to get started</p>
      </div>
    </div>

    <!-- Search + list -->
    <div class="flex-1 max-w-md mx-auto w-full px-4 -mt-4">
      <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-md overflow-hidden">
        <!-- Search -->
        <div class="p-4 border-b border-gray-100 dark:border-gray-800">
          <UInput
            v-model="search"
            placeholder="Search by name..."
            icon="i-heroicons-magnifying-glass"
            size="lg"
            autofocus
          />
        </div>

        <!-- Loading state -->
        <div v-if="userStore.loadingUsers || userStore.pulling" class="py-12 text-center text-gray-400">
          <UIcon name="i-heroicons-arrow-path" class="size-8 animate-spin mx-auto mb-3" />
          <p class="text-sm">{{ userStore.pulling ? 'Syncing from server…' : 'Loading…' }}</p>
        </div>

        <!-- Empty: no users in local DB -->
        <div
          v-else-if="userStore.allUsers.length === 0"
          class="py-12 text-center text-gray-400 px-6"
        >
          <UIcon name="i-heroicons-users" class="size-10 mx-auto mb-3 text-gray-300" />
          <p class="text-sm font-medium text-gray-500">No clinicians found</p>
          <p class="text-xs mt-1">Make sure the device is connected to sync the user list.</p>
          <UButton
            class="mt-4"
            variant="soft"
            icon="i-heroicons-arrow-path"
            :loading="userStore.pulling"
            @click="syncStore.pullUsers()"
          >
            Retry sync
          </UButton>
        </div>

        <!-- Empty search result -->
        <div
          v-else-if="filtered.length === 0"
          class="py-10 text-center text-gray-400 text-sm"
        >
          No clinician matches "{{ search }}"
        </div>

        <!-- User list -->
        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[60vh] overflow-y-auto">
          <li
            v-for="user in filtered"
            :key="user.id"
            class="flex items-center gap-3 px-4 py-3.5 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer transition-colors"
            :class="{ 'bg-primary-50 dark:bg-primary-900/20': currentUser?.id === user.id }"
            @click="select(user)"
          >
            <div class="size-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm shrink-0">
              {{ initials(user) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 dark:text-white truncate">
                {{ user.firstname }} {{ user.lastname }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">@{{ user.username }}</p>
            </div>
            <UIcon
              v-if="currentUser?.id === user.id"
              name="i-heroicons-check-circle-solid"
              class="size-5 text-primary-600 dark:text-primary-400 shrink-0"
            />
          </li>
        </ul>
      </div>

      <!-- Error note -->
      <p
        v-if="userStore.pullError"
        class="mt-3 text-xs text-center text-orange-600 dark:text-orange-400"
      >
        <UIcon name="i-heroicons-exclamation-triangle" class="inline size-3.5 mr-1" />
        {{ userStore.pullError }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSyncStore } from '~/stores/syncStore'
import type { IUserRef } from '~/interfaces/IUserRef'

definePageMeta({ layout: 'setup' })

const userStore = useUserStore()
const syncStore = useSyncStore()
const router = useRouter()

const search = ref('')
const currentUser = computed(() => userStore.currentUser)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return userStore.allUsers
  return userStore.allUsers.filter(u =>
    u.firstname.toLowerCase().includes(q) ||
    u.lastname.toLowerCase().includes(q) ||
    u.username.toLowerCase().includes(q)
  )
})

function initials(user: IUserRef): string {
  return `${user.firstname[0] ?? ''}${user.lastname[0] ?? ''}`.toUpperCase()
}

function select(user: IUserRef) {
  userStore.setCurrentUser(user)
  router.push('/')
}
</script>
