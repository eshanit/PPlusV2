<script setup lang="ts">
interface Props {
  sessionNumber: number
  date: string
  phase: string
}

const props = defineProps<Props>()

function getPhaseColor(phase: string): string {
  if (phase === 'initial_intensive') return 'primary'
  if (phase === 'ongoing') return 'success'
  return 'warning'
}
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
    <div class="flex items-center justify-between">
      <div>
        <p class="font-medium text-gray-900 dark:text-white">
          Session {{ sessionNumber }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ date }}
        </p>
      </div>
      <UBadge
        :label="phase"
        :color="getPhaseColor(phase)"
        variant="soft"
        size="sm"
      />
    </div>
  </div>
</template>