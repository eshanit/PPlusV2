<script setup lang="ts">
import {
  MENTOR_LABELS,
  NA_LABEL,
  SCORE_LABELS,
  scoreBackground,
  scoreTextColor,
} from '~/data/evaluationItemData'

// Mirrors the paper tool's legend: each score is defined from the mentee's
// side and from the mentor's level of involvement.
const rows: { score: 1 | 2 | 3 | 4 | 5 | null; mentee: string; mentor: string }[] = [
  ...([1, 2, 3, 4, 5] as const).map(score => ({
    score,
    mentee: SCORE_LABELS[score],
    mentor: MENTOR_LABELS[score],
  })),
  { score: null, mentee: NA_LABEL, mentor: '—' },
]
</script>

<template>
  <section class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <h3 class="border-b border-gray-200 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:text-gray-300">
      Score legend
    </h3>
    <table class="w-full text-left text-sm">
      <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
        <tr>
          <th scope="col" class="w-16 px-3 py-2 font-semibold">Score</th>
          <th scope="col" class="px-3 py-2 font-semibold">Mentee</th>
          <th scope="col" class="px-3 py-2 font-semibold">Mentor</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <tr v-for="row in rows" :key="row.score ?? 'na'">
          <td class="px-3 py-2">
            <span
              class="inline-flex min-w-10 items-center justify-center rounded-md px-2 py-1 text-sm font-bold"
              :style="{ backgroundColor: scoreBackground(row.score), color: scoreTextColor(row.score) }"
            >
              {{ row.score ?? 'N/A' }}
            </span>
          </td>
          <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ row.mentee }}</td>
          <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ row.mentor }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
