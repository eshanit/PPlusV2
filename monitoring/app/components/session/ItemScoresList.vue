<script setup lang="ts">
import ScoreChip from './ScoreChip.vue'
import { getScoreChangeDisplay } from '~/composables/useSessionCalculations'

interface ItemScore {
  itemSlug: string
  menteeScore: number | null
  notes?: string
  /** False when the item was planned but never scored. Defaults to true. */
  evaluated?: boolean
}

interface ItemMetadata {
  slug: string
  number: string
  title: string
  isAdvanced?: boolean
}

interface Props {
  title: string
  items: ItemScore[]
  itemMetadata: ItemMetadata[]
  /** Previous score per item slug, to show Improved / Regressed / No change. */
  previousScores?: Record<string, number | null>
  colorClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  previousScores: undefined,
  colorClass: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800',
})

const metadataBySlug = computed(() => new Map(props.itemMetadata.map(m => [m.slug, m])))

const rows = computed(() => props.items.map((score) => {
  const meta = metadataBySlug.value.get(score.itemSlug) ?? { slug: score.itemSlug, number: '', title: score.itemSlug }
  const previous = props.previousScores?.[score.itemSlug]
  return {
    score,
    meta,
    previous,
    change: getScoreChangeDisplay(score.menteeScore, previous),
  }
}))
</script>

<template>
  <div>
    <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-3">
      {{ title }}
    </h3>
    <div class="space-y-2">
      <div
        v-for="{ score, meta, previous, change } in rows"
        :key="score.itemSlug"
        class="rounded-lg border p-3"
        :class="meta.isAdvanced
          ? 'border-gray-400 border-l-4 bg-gray-200 dark:border-gray-600 dark:bg-gray-800'
          : props.colorClass"
      >
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:gap-4">
          <div class="flex min-w-0 flex-1 items-start gap-2">
            <span
              class="inline-flex min-w-10 shrink-0 items-center justify-center rounded px-2 py-1 text-sm font-bold"
              :class="meta.isAdvanced
                ? 'bg-gray-700 text-white dark:bg-gray-600'
                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'"
            >
              {{ meta.number }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-base leading-snug text-gray-900 dark:text-white">
                {{ meta.title }}
              </p>
              <span
                v-if="meta.isAdvanced"
                class="mt-1 inline-block rounded-full bg-gray-700 px-2 py-0.5 text-xs font-semibold uppercase text-white dark:bg-gray-600"
                title="Not required to be achieved to be considered a competent provider"
              >
                Advanced
              </span>
              <p v-if="score.notes" class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold">Note:</span> {{ score.notes }}
              </p>
            </div>
          </div>

          <div class="flex shrink-0 flex-col items-start gap-1 md:w-[19rem] md:items-end">
            <ScoreChip :score="score.menteeScore" :evaluated="score.evaluated ?? true" size="sm" />
            <span
              v-if="change"
              class="inline-flex items-center gap-1 text-sm font-semibold"
              :class="change.class"
            >
              <UIcon :name="change.icon" class="size-4" />
              {{ change.label }}
              <span class="font-normal text-gray-500 dark:text-gray-400">from {{ previous }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
