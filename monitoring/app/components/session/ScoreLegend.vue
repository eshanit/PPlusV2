<script setup lang="ts">
import { NA_LABEL, SCORE_COLORS, SCORE_LABELS } from '~/data/evaluationItemData'

interface LegendItem {
  score: number | null
  label: string
  color: string
}

const legendItems: LegendItem[] = [
  { score: 1, label: SCORE_LABELS[1], color: SCORE_COLORS[1] },
  { score: 2, label: SCORE_LABELS[2], color: SCORE_COLORS[2] },
  { score: 3, label: SCORE_LABELS[3], color: SCORE_COLORS[3] },
  { score: 4, label: SCORE_LABELS[4], color: SCORE_COLORS[4] },
  { score: 5, label: SCORE_LABELS[5], color: SCORE_COLORS[5] },
  { score: null, label: NA_LABEL, color: SCORE_COLORS[0] },
]
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
      Score Legend
    </h3>
    <div class="space-y-2">
      <div v-for="item in legendItems" :key="item.score ?? 'na'" class="flex items-center gap-2">
        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.color }" />
        <span class="text-xs text-gray-600 dark:text-gray-300">
          {{ item.score ?? 'N/A' }} - {{ item.label }}
        </span>
      </div>
    </div>
  </div>
</template>