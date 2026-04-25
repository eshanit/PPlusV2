<script setup lang="ts">
interface LegendItem {
  score: number | null
  label: string
  color: string
}

const legendItems: LegendItem[] = [
  { score: 1, label: 'Does not demonstrate competency', color: 'bg-red-500' },
  { score: 2, label: 'Demonstrates basic competency', color: 'bg-orange-500' },
  { score: 3, label: 'Demonstrates satisfactory competency', color: 'bg-blue-500' },
  { score: 4, label: 'Demonstrates advanced competency', color: 'bg-green-500' },
  { score: 5, label: 'Demonstrates exceptional competency', color: 'bg-teal-500' },
  { score: null, label: 'Cannot be evaluated', color: 'bg-gray-400' },
]
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
      Score Legend
    </h3>
    <div class="space-y-2">
      <div v-for="item in legendItems" :key="item.score" class="flex items-center gap-2">
        <div class="w-3 h-3 rounded-full" :class="item.color" />
        <span class="text-xs text-gray-600 dark:text-gray-300">
          {{ item.score ?? 'N/A' }} - {{ item.label }}
        </span>
      </div>
    </div>
  </div>
</template>