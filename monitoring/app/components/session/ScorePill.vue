<script setup lang="ts">
interface Props {
  score: number | null
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
})

function getScoreColor(score: number | null): string {
  if (score === null) return 'bg-gray-100 dark:bg-gray-800 text-gray-500'
  if (score === 1) return 'bg-red-500 text-white'
  if (score === 2) return 'bg-orange-500 text-white'
  if (score === 3) return 'bg-blue-500 text-white'
  if (score === 4) return 'bg-green-500 text-white'
  if (score === 5) return 'bg-teal-500 text-white'
  return 'bg-gray-500 text-white'
}

const sizeClasses = {
  sm: 'w-6 h-6 text-[10px]',
  md: 'w-8 h-8 text-xs',
  lg: 'w-10 h-10 text-sm',
}
</script>

<template>
  <div
    class="rounded-lg flex items-center justify-center font-bold text-white"
    :class="[sizeClasses[props.size], getScoreColor(props.score)]"
  >
    {{ props.score ?? 'N/A' }}
  </div>
</template>