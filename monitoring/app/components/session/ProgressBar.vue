<script setup lang="ts">
import type { IEvalItem } from '~/interfaces/IEvalItem'

interface Props {
  items: (IEvalItem & { type: 'tool' | 'counselling' })[]
  currentIndex: number
  scores: Record<string, number | null>
  currentItemScored?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  currentItemScored: true,
})

const emit = defineEmits<{
  'goto': [index: number]
  'prev': []
  'next': []
}>()

const total = computed(() => props.items.length)
const progress = computed(() => `${props.currentIndex + 1} / ${total.value}`)
const currentTitle = computed(() => props.items[props.currentIndex]?.title ?? '')
const isFirst = computed(() => props.currentIndex === 0)
const isLast = computed(() => props.currentIndex === total.value - 1)

function getScore(slug: string): number | null {
  return props.scores[slug] ?? null
}
</script>

<template>
  <div class="sticky top-0 bg-white dark:bg-gray-900 z-10 border-b border-gray-100 dark:border-gray-800">
    <!-- Navigation row -->
    <div class="flex items-center justify-between px-4 py-2">
      <UButton
        variant="ghost"
        size="xs"
        :disabled="isFirst"
        @click="emit('prev')"
      >
        <UIcon name="i-heroicons-chevron-left" class="w-4 h-4" />
      </UButton>
      
      <div class="text-center">
        <p class="text-xs font-medium text-gray-900 dark:text-white">{{ progress }}</p>
        <p class="text-xs text-gray-500 truncate max-w-[200px]">{{ currentTitle }}</p>
      </div>
      
      <UButton
        variant="ghost"
        size="xs"
        :disabled="isLast || !currentItemScored"
        @click="emit('next')"
      >
        <UIcon name="i-heroicons-chevron-right" class="w-4 h-4" />
      </UButton>
    </div>
    
    <!-- Progress dots -->
    <div class="flex justify-center gap-1 px-4 pb-3">
      <button
        v-for="(item, idx) in items"
        :key="item.slug"
        class="w-2 h-2 rounded-full transition-colors"
        :class="idx === currentIndex 
          ? 'bg-primary-500' 
          : getScore(item.slug) 
            ? 'bg-primary-300' 
            : 'bg-gray-200 dark:bg-gray-700'"
        @click="emit('goto', idx)"
      />
    </div>
  </div>
</template>