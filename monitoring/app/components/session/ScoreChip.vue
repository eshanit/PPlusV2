<script setup lang="ts">
import { NA_LABEL, SCORE_LABELS, SCORE_SHORT_LABELS, scoreBackground, scoreTextColor } from '~/data/evaluationItemData'

/**
 * A score with its meaning, e.g. "4 · Demonstrates advanced competency".
 * Uses the legend colour as the background so the chip always matches the
 * score buttons and legend, with text colours chosen for contrast.
 */
const props = withDefaults(defineProps<{
  score: number | null | undefined
  /** False when the item was planned but never scored, which differs from N/A. */
  evaluated?: boolean
  /** Use the one-word label instead of the full sentence. */
  short?: boolean
  size?: 'sm' | 'md'
}>(), {
  evaluated: true,
  short: false,
  size: 'md',
})

const isNotScored = computed(() => !props.evaluated || props.score === undefined)

const label = computed(() => {
  if (isNotScored.value) return 'Not scored'
  if (props.score === null) return props.short ? 'N/A' : NA_LABEL
  const score = props.score as 1 | 2 | 3 | 4 | 5
  return props.short ? SCORE_SHORT_LABELS[score] : SCORE_LABELS[score]
})

const style = computed(() => isNotScored.value
  ? undefined
  : { backgroundColor: scoreBackground(props.score), color: scoreTextColor(props.score) })
</script>

<template>
  <span
    class="inline-flex max-w-full items-center gap-2 rounded-md font-bold"
    :class="[
      size === 'sm' ? 'px-2 py-0.5 text-sm' : 'px-2.5 py-1 text-base',
      isNotScored ? 'border-2 border-dashed border-gray-400 text-gray-600 dark:border-gray-500 dark:text-gray-300' : '',
    ]"
    :style="style"
  >
    <template v-if="!isNotScored">
      <span>{{ score === null ? 'N/A' : score }}</span>
      <span v-if="score !== null || !short" aria-hidden="true">·</span>
    </template>
    <span v-if="isNotScored || score !== null || !short" class="min-w-0">{{ label }}</span>
  </span>
</template>
