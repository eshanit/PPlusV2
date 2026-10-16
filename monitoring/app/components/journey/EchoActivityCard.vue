<script setup lang="ts">
import type { ISession } from '~/interfaces/ISession'
import { echoAccuracy, formatDate } from '~/composables/useSessionCalculations'

const props = defineProps<{
  /** Sessions in this journey, any order. */
  sessions: ISession[]
}>()

// Only visits where both counts were recorded. Older sessions may lack them.
const visits = computed(() => props.sessions
  .filter(s => s.echocardiogramsPerformed !== undefined && s.echocardiogramsCorrectDiagnosis !== undefined)
  .sort((a, b) => a.evalDate - b.evalDate || a.createdAt - b.createdAt)
  .map(s => ({
    id: s._id,
    date: formatDate(s.evalDate),
    accuracy: echoAccuracy(s.echocardiogramsPerformed, s.echocardiogramsCorrectDiagnosis)!,
  })))

const totals = computed(() => {
  const performed = visits.value.reduce((sum, v) => sum + v.accuracy.performed, 0)
  const correct = visits.value.reduce((sum, v) => sum + v.accuracy.correct, 0)
  return echoAccuracy(performed, correct)!
})

const missingCount = computed(() => props.sessions.length - visits.value.length)
</script>

<template>
  <section class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950/30">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Supervised echocardiograms</h2>
    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Totals across this journey</p>

    <div v-if="visits.length" class="mt-3 space-y-3">
      <div class="grid grid-cols-3 gap-3 text-center">
        <div class="rounded-lg bg-white/80 p-3 dark:bg-gray-900/50">
          <p class="text-2xl font-bold text-sky-900 dark:text-sky-100">{{ totals.performed }}</p>
          <p class="text-sm text-gray-600 dark:text-gray-300">Performed</p>
        </div>
        <div class="rounded-lg bg-white/80 p-3 dark:bg-gray-900/50">
          <p class="text-2xl font-bold text-sky-900 dark:text-sky-100">{{ totals.correct }}</p>
          <p class="text-sm text-gray-600 dark:text-gray-300">Correct diagnosis</p>
        </div>
        <div class="rounded-lg bg-white/80 p-3 dark:bg-gray-900/50">
          <p class="text-2xl font-bold text-sky-900 dark:text-sky-100">{{ totals.percent === null ? '—' : `${totals.percent}%` }}</p>
          <p class="text-sm text-gray-600 dark:text-gray-300">Overall accuracy</p>
        </div>
      </div>

      <div>
        <p class="mb-1.5 text-sm font-semibold text-gray-700 dark:text-gray-300">By visit, oldest first</p>
        <ul class="divide-y divide-sky-100 rounded-lg bg-white/80 dark:divide-sky-900 dark:bg-gray-900/50">
          <li v-for="visit in visits" :key="visit.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
            <span class="text-gray-600 dark:text-gray-400">{{ visit.date }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ visit.accuracy.text }}</span>
          </li>
        </ul>
      </div>
    </div>

    <p v-else class="mt-3 text-sm text-gray-600 dark:text-gray-300">No echocardiogram counts recorded yet.</p>

    <p v-if="missingCount > 0 && visits.length" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
      {{ missingCount }} earlier session{{ missingCount === 1 ? ' has' : 's have' }} no counts recorded.
    </p>
  </section>
</template>
