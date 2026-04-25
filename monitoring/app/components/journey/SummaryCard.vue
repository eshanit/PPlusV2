<script setup lang="ts">
interface Props {
  totalSessions: number
  averageScore: string
  latestDate: string
}

defineProps<Props>()
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
    <div class="grid grid-cols-3 gap-4 text-center">
      <div>
        <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ totalSessions }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Sessions</p>
      </div>
      <div>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ averageScore }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Latest Avg</p>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ latestDate }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Latest Date</p>
      </div>
    </div>
  </div>
</template>