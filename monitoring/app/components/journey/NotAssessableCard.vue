<script setup lang="ts">
import { format } from 'date-fns'
import type { ISession } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import type { IItemScore } from '~/interfaces/IItemScore'
import { notAssessableLabel } from '~/data/evaluationItemData'
import { getCompetencyBreakdown } from '~/composables/useCompetency'
import { useSessionStore } from '~/stores/sessionStore'

/**
 * Competencies marked not assessable at the mentee's facility. They don't
 * count towards competency until scored. Clearing one removes its marks from
 * the journey's sessions, leaving those entries as N/A, so the item counts
 * again. See NOT_ASSESSABLE.md.
 */
const props = defineProps<{
  sessions: ISession[]
  tool: ITool | null | undefined
}>()

const sessionStore = useSessionStore()
const toast = useToast()

const marked = computed(() => getCompetencyBreakdown(props.sessions, props.tool).notAssessable)

const pendingClear = ref<IEvalItem | null>(null)
const clearing = ref(false)

function withoutMark(scores: IItemScore[] | undefined, slug: string): IItemScore[] {
  return (scores ?? []).map(score => {
    if (score.itemSlug !== slug || !score.notAssessable) return score
    const { notAssessable: _removed, ...rest } = score
    return rest
  })
}

async function clearMark() {
  const item = pendingClear.value
  if (!item) return
  clearing.value = true
  try {
    for (const session of props.sessions) {
      const hasMark = [session.itemScores, ...(session.rounds ?? []).map(round => round.itemScores)]
        .some(list => (list ?? []).some(score => score.itemSlug === item.slug && score.notAssessable))
      if (!hasMark) continue
      await sessionStore.save({
        ...session,
        itemScores: withoutMark(session.itemScores, item.slug),
        rounds: session.rounds?.map(round => ({ ...round, itemScores: withoutMark(round.itemScores, item.slug) })),
        syncStatus: 'pending',
      })
    }
    toast.add({ title: `${item.number} counts again`, description: 'It now needs a score of 4 or 5 for competency.', color: 'success' })
    pendingClear.value = null
  } catch (error) {
    toast.add({ title: 'Could not clear', description: String(error), color: 'error' })
  } finally {
    clearing.value = false
  }
}
</script>

<template>
  <section
    v-if="marked.length"
    class="space-y-3 rounded-xl border border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-gray-900"
  >
    <div>
      <h2 class="flex items-center gap-1.5 text-base font-semibold text-gray-900 dark:text-white">
        <UIcon name="i-heroicons-no-symbol" class="size-5 text-slate-600 dark:text-slate-300" />
        Not assessable here ({{ marked.length }})
      </h2>
      <p class="text-sm text-gray-600 dark:text-gray-300">
        These don't count towards competency for this mentee. Scoring one in a later session, or clearing it here, makes it count again.
      </p>
    </div>
    <ul class="space-y-1.5">
      <li
        v-for="{ item, entry } in marked"
        :key="item.slug"
        class="flex flex-wrap items-start gap-2.5 rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800"
      >
        <span class="min-w-10 shrink-0 rounded-md bg-white px-1.5 py-0.5 text-center text-sm font-bold text-gray-900 dark:bg-gray-900 dark:text-white">{{ item.number }}</span>
        <span class="min-w-0 flex-1 text-sm leading-snug text-gray-900 dark:text-gray-100">
          {{ item.title }}
          <span class="mt-0.5 block font-semibold text-slate-800 dark:text-slate-200">{{ notAssessableLabel(entry.reason) }}</span>
          <span v-if="entry.note" class="block text-slate-700 dark:text-slate-300">{{ entry.note }}</span>
          <span class="block text-xs text-gray-500 dark:text-gray-400">
            Marked {{ format(new Date(entry.evalDate), 'd MMM yyyy') }}<template v-if="entry.evaluatorName"> by {{ entry.evaluatorName }}</template>
          </span>
        </span>
        <UButton size="sm" color="neutral" variant="outline" class="shrink-0" @click="pendingClear = item">Clear</UButton>
      </li>
    </ul>

    <UModal
      :open="pendingClear !== null"
      :title="`Make ${pendingClear?.number} count again?`"
      description="It will need a score of 4 or 5 before the mentee can be competent. Earlier sessions keep their scores; the not-assessable marks become N/A."
      @update:open="(open: boolean) => { if (!open) pendingClear = null }"
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" @click="pendingClear = null">Cancel</UButton>
          <UButton color="primary" :loading="clearing" @click="clearMark">Clear</UButton>
        </div>
      </template>
    </UModal>
  </section>
</template>
