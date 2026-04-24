<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <header class="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between">
        <NuxtLink to="/" class="flex items-center gap-2 text-primary-600 dark:text-primary-400">
          <UIcon name="i-heroicons-heart" class="size-5" />
          <span class="font-semibold text-sm tracking-wide">PenPlus NCD</span>
        </NuxtLink>

        <NuxtLink to="/setup" class="flex items-center gap-2 group" title="Change identity">
          <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors hidden sm:block">
            {{ userStore.currentUserFullName }}
          </span>
          <div class="size-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-xs ring-2 ring-transparent group-hover:ring-primary-400 transition-all">
            {{ userStore.currentUserInitials }}
          </div>
        </NuxtLink>
      </div>
    </header>

    <!-- Offline/error banner -->
    <div
      v-if="syncStore.hasError"
      class="bg-orange-50 dark:bg-orange-950 border-b border-orange-200 dark:border-orange-800 px-4 py-2 text-center text-xs text-orange-700 dark:text-orange-300"
    >
      <UIcon name="i-heroicons-exclamation-triangle" class="size-3.5 inline-block mr-1" />
      Offline — changes will sync when connection is restored
    </div>

    <main class="flex-1 max-w-2xl mx-auto w-full px-4 py-6">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSyncStore } from '~/stores/syncStore'

const userStore = useUserStore()
const syncStore = useSyncStore()
</script>
