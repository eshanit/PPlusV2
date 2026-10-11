<script setup lang="ts">
import { SCORE_COLORS } from '~/data/evaluationItemData'

interface Props {
  score: number | null
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
})

function getScoreColor(score: number | null): string {
  if (score === null) return 'text-gray-500'
  return 'text-white'
}

const sizeClasses = {
  sm: 'w-6 h-6 text-[10px]',
  md: 'w-8 h-8 text-xs',
  lg: 'w-10 h-10 text-sm',
}

function getBackgroundColor(score: number | null): string {
  if (score === null) return '#cbd5e1'
  return SCORE_COLORS[score as 1 | 2 | 3 | 4 | 5]
}
</script>

<template>
  <div
    class="rounded-lg flex items-center justify-center font-bold"
    :class="[sizeClasses[props.size], getScoreColor(props.score)]"
    :style="{ backgroundColor: getBackgroundColor(props.score) }"
  >
    {{ props.score ?? 'N/A' }}
  </div>
</template>