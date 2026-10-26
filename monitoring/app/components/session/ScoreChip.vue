<script setup lang="ts">
import type { NotAssessableReason } from '~/interfaces/IItemScore'
import { MENTOR_LABELS, NA_LABEL, SCORE_LABELS, SCORE_SHORT_LABELS, notAssessableLabel, scoreBackground, scoreTextColor } from '~/data/evaluationItemData'

/**
 * A score with its meaning. The full form shows both sides of the paper
 * tool's legend, labelled Mentee and Mentor; the short form shows one word.
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
  /** Set when the item is marked not assessable; shown instead of the score. */
  notAssessable?: NotAssessableReason
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

// Full form for a 1-5 score: show the mentee and mentor meanings together.
const showBoth = computed(() => !props.short && !isNotScored.value && props.score !== null)
const mentorLabel = computed(() => showBoth.value
  ? MENTOR_LABELS[props.score as 1 | 2 | 3 | 4 | 5]
  : undefined)

const style = computed(() => isNotScored.value
  ? undefined
  : { backgroundColor: scoreBackground(props.score), color: scoreTextColor(props.score) })
</script>

<template>
  <!-- Not assessable at the mentee's facility -->
  <span
    v-if="notAssessable"
    class="inline-flex max-w-full items-center gap-1.5 rounded-md border-2 border-slate-400 bg-slate-100 font-bold text-slate-800 dark:border-slate-500 dark:bg-slate-800 dark:text-slate-100"
    :class="size === 'sm' ? 'px-2 py-0.5 text-sm' : 'px-2.5 py-1 text-base'"
  >
    <UIcon name="i-heroicons-no-symbol" class="size-4 shrink-0" aria-hidden="true" />
    <span class="min-w-0">{{ short ? 'Not assessable' : `Not assessable · ${notAssessableLabel(notAssessable)}` }}</span>
  </span>

  <!-- Full form: score plus labelled Mentee and Mentor meanings -->
  <span
    v-else-if="showBoth"
    class="inline-flex max-w-full items-start gap-2.5 rounded-md text-left"
    :class="size === 'sm' ? 'px-2 py-1 text-sm' : 'px-2.5 py-1.5 text-base'"
    :style="style"
  >
    <span class="font-bold" :class="size === 'sm' ? 'text-base' : 'text-lg'">{{ score }}</span>
    <span class="flex min-w-0 flex-col leading-snug">
      <span><span class="font-bold">Mentee</span><UIcon name="i-heroicons-arrow-right" class="mx-1 inline-block size-4 shrink-0 align-[-0.125em]" aria-hidden="true" />{{ label }}</span>
      <span><span class="font-bold">Mentor</span><UIcon name="i-heroicons-arrow-right" class="mx-1 inline-block size-4 shrink-0 align-[-0.125em]" aria-hidden="true" />{{ mentorLabel }}</span>
    </span>
  </span>

  <!-- Short form, N/A and Not scored -->
  <span
    v-else
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
