<script setup lang="ts">
import { SCORE_COLORS } from '~/data/evaluationItemData'

interface Props {
  counts: Record<string, number>
}

defineProps<Props>()

const scoreItems: { score: number | null; label: string }[] = [
  { score: 1, label: '1' },
  { score: 2, label: '2' },
  { score: 3, label: '3' },
  { score: 4, label: '4' },
  { score: 5, label: '5' },
  { score: null, label: 'N/A' },
]

function getScoreColor(score: number | null): string {
  if (score === null) return 'text-gray-600'
  return 'text-white'
}

function getScoreBackground(score: number | null): string {
  if (score === null) return SCORE_COLORS[0]
  return SCORE_COLORS[score as 1 | 2 | 3 | 4 | 5]
}
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
      Score Distribution
    </h3>
    <div class="grid grid-cols-6 gap-2 text-center">
      <div v-for="item in scoreItems" :key="item.label" class="text-center">
        <div
          class="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm mx-auto"
          :class="getScoreColor(item.score)"
          :style="{ backgroundColor: getScoreBackground(item.score) }"
        >
          {{ item.label }}
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ counts[item.label] ?? 0 }}</p>
      </div>
    </div>
  </div>
</template>