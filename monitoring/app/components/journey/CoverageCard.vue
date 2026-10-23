<script setup lang="ts">
import { format } from 'date-fns'
import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'
import { getJourneyCoverage, STALE_AFTER_SESSIONS } from '~/composables/useCoverage'
import { useUserStore } from '~/stores/userStore'

/**
 * What a journey still needs covering, regardless of who evaluated, so an
 * evaluator picking up a mentee from a colleague can see what is left.
 */
const props = defineProps<{
  sessions: ISession[]
  tool: ITool | null | undefined
}>()

const userStore = useUserStore()
const coverage = computed(() => getJourneyCoverage(props.sessions, props.tool))

const lastSessionLine = computed(() => {
  const last = coverage.value.lastSession
  if (!last) return null
  const byMe = last.evaluatorId === userStore.currentUser?.id
  return `Last evaluated ${format(new Date(last.date), 'd MMM yyyy')} by ${byMe ? 'you' : last.evaluatorName}`
})

const evaluators = computed(() => {
  const names = new Set(props.sessions.map(s => `${s.evaluator.firstname} ${s.evaluator.lastname}`.trim()))
  return [...names]
})

const allCovered = computed(() =>
  coverage.value.neverRequired.length === 0 && coverage.value.staleRequired.length === 0)

function staleLabel(sessionsSince: number) {
  return `not rated for ${sessionsSince} session${sessionsSince === 1 ? '' : 's'}`
}
</script>

<template>
  <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Competency coverage</h2>
        <p v-if="lastSessionLine" class="text-sm text-gray-600 dark:text-gray-300">
          {{ lastSessionLine }}<template v-if="evaluators.length > 1"> · evaluated by {{ evaluators.length }} people</template>
        </p>
      </div>
      <span
        class="rounded-full px-3 py-1 text-sm font-semibold"
        :class="allCovered
          ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
          : 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'"
      >
        {{ allCovered ? 'Nothing missed' : `${coverage.neverRequired.length + coverage.staleRequired.length} to cover` }}
      </span>
    </div>

    <div v-if="coverage.neverRequired.length">
      <h3 class="mb-1.5 text-sm font-semibold text-red-700 dark:text-red-300">
        Not yet evaluated ({{ coverage.neverRequired.length }})
      </h3>
      <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
        Required items with no score yet. The mentee cannot reach competency until they are evaluated.
      </p>
      <ul class="space-y-1.5">
        <li v-for="c in coverage.neverRequired" :key="c.item.slug" class="flex items-start gap-2.5 rounded-lg bg-red-50 px-3 py-2 dark:bg-red-950/30">
          <span class="min-w-10 shrink-0 rounded-md bg-white px-1.5 py-0.5 text-center text-sm font-bold text-gray-900 dark:bg-gray-800 dark:text-white">{{ c.item.number }}</span>
          <span class="min-w-0 flex-1 text-sm leading-snug text-gray-900 dark:text-gray-100">
            {{ c.item.title }}
            <span v-if="c.status === 'only_na'" class="mt-0.5 block text-xs font-semibold text-red-700 dark:text-red-300">Only marked N/A so far</span>
          </span>
        </li>
      </ul>
    </div>

    <div v-if="coverage.staleRequired.length">
      <h3 class="mb-1.5 text-sm font-semibold text-amber-800 dark:text-amber-300">
        Not evaluated recently ({{ coverage.staleRequired.length }})
      </h3>
      <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
        Below 4 and not rated for {{ STALE_AFTER_SESSIONS }} or more sessions.
      </p>
      <ul class="space-y-1.5">
        <li v-for="c in coverage.staleRequired" :key="c.item.slug" class="flex items-start gap-2.5 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-950/30">
          <span class="min-w-10 shrink-0 rounded-md bg-white px-1.5 py-0.5 text-center text-sm font-bold text-gray-900 dark:bg-gray-800 dark:text-white">{{ c.item.number }}</span>
          <span class="min-w-0 flex-1 text-sm leading-snug text-gray-900 dark:text-gray-100">
            {{ c.item.title }}
            <span class="mt-0.5 block text-xs text-gray-600 dark:text-gray-300">
              Latest score {{ c.latestScore }}<template v-if="c.lastEvaluatedDate"> on {{ format(new Date(c.lastEvaluatedDate), 'd MMM yyyy') }}</template>, {{ staleLabel(c.sessionsSince) }}
            </span>
          </span>
        </li>
      </ul>
    </div>

    <p v-if="coverage.neverAdvanced.length" class="text-sm text-gray-600 dark:text-gray-300">
      {{ coverage.neverAdvanced.length }} advanced item{{ coverage.neverAdvanced.length === 1 ? ' has' : 's have' }} not been evaluated yet. Advanced items are not required for competency.
    </p>

    <p v-if="allCovered && !coverage.neverAdvanced.length" class="text-sm text-gray-600 dark:text-gray-300">
      Every competency has been evaluated, and nothing below 4 has been left for long.
    </p>
  </section>
</template>
