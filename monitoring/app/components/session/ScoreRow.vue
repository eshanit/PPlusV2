<script setup lang="ts">
import { format } from 'date-fns'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import { NA_LABEL, SCORE_COLORS, SCORE_LABELS } from '~/data/evaluationItemData'

type ScoreValue = 1 | 2 | 3 | 4 | 5 | null

interface PreviousAssessment {
  score: ScoreValue
  evalDate: number
  sessionNumber: number
  roundNumber: number
}

const props = defineProps<{
  item: IEvalItem
  score: ScoreValue
  isScored: boolean
  previousAssessment?: PreviousAssessment
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

const selectedScoreLabel = computed(() => {
  if (!props.isScored) return undefined
  return props.score === null ? NA_LABEL : SCORE_LABELS[props.score]
})

const scoreChange = computed(() => {
  const previousScore = props.previousAssessment?.score
  if (
    !props.isScored
    || props.score === null
    || previousScore === null
    || previousScore === undefined
  ) return undefined

  if (props.score > previousScore) return 'improved'
  if (props.score < previousScore) return 'regressed'
  return 'unchanged'
})

const scoreChangeDisplay = computed(() => {
  if (scoreChange.value === 'improved') {
    return { icon: 'i-heroicons-arrow-trending-up', label: 'Improved', class: 'text-green-700 dark:text-green-400' }
  }
  if (scoreChange.value === 'regressed') {
    return { icon: 'i-heroicons-arrow-trending-down', label: 'Regressed', class: 'text-red-700 dark:text-red-400' }
  }
  if (scoreChange.value === 'unchanged') {
    return { icon: 'i-heroicons-minus', label: 'No change', class: 'text-gray-500 dark:text-gray-400' }
  }
  return undefined
})
</script>

<template>
  <article
    class="rounded-lg border p-3"
    :class="item.isAdvanced
      ? 'border-gray-400 border-l-4 bg-gray-200 dark:border-gray-600 dark:bg-gray-800'
      : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'"
  >
    <div class="flex items-start gap-2">
      <span
        class="inline-flex min-w-9 shrink-0 items-center justify-center rounded px-2 py-1 text-xs font-bold"
        :class="item.isAdvanced
          ? 'bg-gray-700 text-white dark:bg-gray-600'
          : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'"
      >
        {{ item.number }}
      </span>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-sm text-gray-900 dark:text-gray-100">{{ item.title }}</p>
          <span
            v-if="item.isAdvanced"
            class="rounded-full bg-gray-700 px-2 py-0.5 text-[10px] font-semibold uppercase text-white dark:bg-gray-600"
            title="Not required to be achieved to be considered a competent provider"
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

    <div v-if="selectedScoreLabel" class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
      <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ selectedScoreLabel }}
      </p>
      <span
        v-if="scoreChangeDisplay"
        class="inline-flex items-center gap-1 text-xs font-semibold"
        :class="scoreChangeDisplay.class"
        role="status"
        :aria-label="scoreChangeDisplay.label"
      >
        <UIcon :name="scoreChangeDisplay.icon" class="h-4 w-4" />
        {{ scoreChangeDisplay.label }}
      </span>
    </div>

    <div
      v-if="previousAssessment"
      class="mt-2 rounded-md bg-gray-50 px-2.5 py-2 text-xs text-gray-600 dark:bg-gray-900/70 dark:text-gray-300"
    >
      <span class="font-semibold">Previous score:</span>
      {{ previousAssessment.score === null ? 'N/A' : `${previousAssessment.score} — ${SCORE_LABELS[previousAssessment.score]}` }}
      <span class="mt-0.5 block text-gray-500 dark:text-gray-400">
        {{ format(new Date(previousAssessment.evalDate), 'd MMM yyyy') }}
        · Session {{ previousAssessment.sessionNumber }}
        · Round {{ previousAssessment.roundNumber }}
      </span>
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
