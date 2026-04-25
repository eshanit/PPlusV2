<script setup lang="ts">
import ScorePill from './ScorePill.vue'

interface ItemScore {
  itemSlug: string
  menteeScore: number | null
  notes?: string
}

interface Props {
  title: string
  items: ItemScore[]
  itemMetadata: { slug: string; number: string; title: string }[]
  colorClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  colorClass: 'bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800',
})

function getMetadata(slug: string) {
  return props.itemMetadata.find(m => m.slug === slug) ?? { slug, number: '', title: slug }
}
</script>

<template>
  <div>
    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
      {{ title }}
    </h3>
    <div class="space-y-2">
      <div
        v-for="score in items"
        :key="score.itemSlug"
        class="rounded-lg border p-3"
        :class="props.colorClass"
      >
        <div class="flex items-center gap-3">
          <ScorePill :score="score.menteeScore" size="md" />
          <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ getMetadata(score.itemSlug).number }}</p>
            <p class="text-sm font-medium text-gray-900 dark:text-white leading-tight">
              {{ getMetadata(score.itemSlug).title }}
            </p>
            <p v-if="score.notes" class="text-xs text-gray-400 dark:text-gray-500 mt-1 italic">
              {{ score.notes }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>