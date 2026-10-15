<script setup lang="ts">
import { scoreBackground, scoreTextColor } from '~/data/evaluationItemData'

interface Props {
  score: number | null
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
})

const sizeClasses = {
  sm: 'w-6 h-6 text-xs',
  md: 'w-8 h-8 text-xs',
  lg: 'w-10 h-10 text-sm',
}

</script>

<template>
  <div
    class="rounded-lg flex items-center justify-center font-bold"
    :class="sizeClasses[props.size]"
    :style="{ backgroundColor: scoreBackground(props.score), color: scoreTextColor(props.score) }"
  >
    {{ props.score ?? 'N/A' }}
  </div>
</template>