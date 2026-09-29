<script setup lang="ts">
interface Props {
  sessionNumber: number
  date: string
  phase: string
  roundLabel?: string
  reopenReason?: string
}

const props = defineProps<Props>()

type BadgeColor = 'primary' | 'success' | 'warning' | 'neutral'

function getPhaseColor(phase: string): BadgeColor {
  if (phase === 'initial_intensive') return 'primary'
  if (phase === 'ongoing') return 'success'
  return 'warning'
}
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
    <div class="flex items-center justify-between">
      <div>
        <p class="font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
          Session {{ sessionNumber }}
          <span
            v-if="roundLabel"
            class="text-[10px] font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 rounded-full px-1.5 py-0.5"
          >
            {{ roundLabel }}
          </span>
          <span
            v-if="reopenReason"
            class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 rounded-full px-1.5 py-0.5"
            :title="`Reopened: ${reopenReason}`"
          >
            Reopened
          </span>
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