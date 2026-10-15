<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <header class="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex min-w-0 items-center gap-3">
          <UButton
            v-if="canGoBack"
            variant="soft"
            color="neutral"
            icon="i-heroicons-arrow-left"
            label="Back"
            class="-ml-1"
            @click="goBack"
          />
          <NuxtLink to="/" class="flex items-center gap-2 text-primary-600 dark:text-primary-400">
            <UIcon name="i-heroicons-heart" class="size-6 shrink-0" />
            <span class="font-semibold text-base tracking-wide" :class="canGoBack ? 'hidden sm:inline' : ''">PenPlus NCD</span>
          </NuxtLink>
        </div>

        <div class="flex items-center gap-2">
          <UButton
            to="/sync"
            variant="soft"
            color="neutral"
            size="lg"
            icon="i-heroicons-arrow-path"
            aria-label="Sync"
            title="Sync"
          />

          <NuxtLink to="/setup" class="flex items-center gap-2 group" title="Change identity">
            <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors hidden sm:block">
              {{ userStore.currentUserFullName }}
            </span>
            <div class="size-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm ring-2 ring-transparent group-hover:ring-primary-400 transition-all">
              {{ userStore.currentUserInitials }}
            </div>
          </NuxtLink>
        </div>
      </div>
    </header>

    <!-- Offline/error banner -->
    <div
      v-if="syncStore.hasError"
      class="bg-orange-50 dark:bg-orange-950 border-b border-orange-200 dark:border-orange-800 px-4 py-2 text-center text-sm text-orange-700 dark:text-orange-300"
    >
      <UIcon name="i-heroicons-exclamation-triangle" class="size-4 inline-block mr-1" />
      Offline — changes will sync when connection is restored
    </div>

    <main class="flex-1 max-w-4xl mx-auto w-full px-4 py-6">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSyncStore } from '~/stores/syncStore'

const userStore = useUserStore()
const syncStore = useSyncStore()
const { canGoBack, goBack } = useBackNavigation()
</script>
