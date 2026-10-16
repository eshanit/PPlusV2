<script setup lang="ts">
import { echoAccuracy } from '~/composables/useSessionCalculations'

const props = defineProps<{
  performed?: number
  correct?: number
  error?: string
}>()

const accuracy = computed(() => echoAccuracy(props.performed, props.correct))

const tiles = computed(() => [
  { key: 'performed', label: 'Performed under supervision', value: props.performed ?? 'Not recorded' },
  { key: 'correct', label: 'Correct final diagnosis', value: props.correct ?? 'Not recorded' },
  {
    key: 'accuracy',
    label: 'Diagnostic accuracy',
    value: accuracy.value
      ? (accuracy.value.percent === null ? '—' : `${accuracy.value.percent}%`)
      : 'Not recorded',
  },
])
</script>

<template>
  <section class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950/30">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Echocardiogram activity · whole visit</h2>
    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
      Supervised counts for this visit. They do not affect competency status.
    </p>
    <div class="mt-3 grid grid-cols-3 gap-3 text-center">
      <div v-for="tile in tiles" :key="tile.key" class="rounded-lg bg-white/80 p-3 dark:bg-gray-900/50">
        <p class="text-2xl font-bold text-sky-900 dark:text-sky-100">{{ tile.value }}</p>
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ tile.label }}</p>
      </div>
    </div>
    <p v-if="accuracy && accuracy.percent !== null" class="mt-2 text-sm text-gray-700 dark:text-gray-300">
      {{ accuracy.text }}
    </p>
    <p v-if="error" class="mt-2 text-sm font-medium text-red-700 dark:text-red-300" role="alert">
      {{ error }}
    </p>
  </section>
</template>
