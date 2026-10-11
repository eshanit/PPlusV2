<script setup lang="ts">
import type { IEvalItem } from '~/interfaces/IEvalItem'
import { NA_LABEL, SCORE_COLORS } from '~/data/evaluationItemData'

type ScoreValue = 1 | 2 | 3 | 4 | 5 | null

const props = defineProps<{
  item: IEvalItem
  score: ScoreValue
  isScored: boolean
  note?: string
  counselling?: boolean
}>()

const emit = defineEmits<{
  score: [value: ScoreValue]
  note: [value: string]
}>()

const scoreOptions: { value: ScoreValue; label: string }[] = [
  { value: 1, label: '1' },
  { value: 2, label: '2' },
  { value: 3, label: '3' },
  { value: 4, label: '4' },
  { value: 5, label: '5' },
  { value: null, label: 'N/A' },
]

function backgroundColor(value: ScoreValue): string {
  if (value === null) return SCORE_COLORS[0]
  return SCORE_COLORS[value]
}
</script>

<template>
  <article
    class="rounded-lg border p-3"
    :class="item.isAdvanced
      ? 'border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800'
      : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'"
  >
    <div class="flex items-start gap-2">
      <span
        class="inline-flex min-w-9 shrink-0 items-center justify-center rounded px-2 py-1 text-xs font-bold"
        :class="item.isAdvanced
          ? 'bg-gray-300 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
          : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'"
      >
        {{ item.number }}
      </span>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-sm text-gray-900 dark:text-gray-100">{{ item.title }}</p>
          <span
            v-if="item.isAdvanced"
            class="rounded-full bg-gray-300 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-200"
          >
            Advanced
          </span>
          <span
            v-if="counselling"
            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
          >
            Counselling
          </span>
        </div>
      </div>
    </div>

    <div class="mt-3 grid grid-cols-6 gap-1.5" role="group" :aria-label="`Score for ${item.number}`">
      <button
        v-for="option in scoreOptions"
        :key="option.label"
        type="button"
        class="min-h-10 rounded-md border px-1 text-xs font-semibold transition-opacity"
        :class="isScored && score === option.value
          ? 'border-gray-900 text-white ring-2 ring-gray-900/20 dark:border-white dark:ring-white/20'
          : 'border-gray-200 text-gray-700 opacity-65 hover:opacity-100 dark:border-gray-600 dark:text-gray-200'"
        :style="{ backgroundColor: isScored && score === option.value ? backgroundColor(option.value) : undefined }"
        :aria-pressed="isScored && score === option.value"
        :title="option.value === null ? NA_LABEL : `Score ${option.value}`"
        @click="emit('score', option.value)"
      >
        {{ option.label }}
      </button>
    </div>

    <UTextarea
      :model-value="note ?? ''"
      class="mt-2 w-full"
      :rows="2"
      size="sm"
      placeholder="Item notes (optional)"
      :aria-label="`Notes for ${item.number}`"
      @update:model-value="emit('note', String($event))"
    />
  </article>
</template>
