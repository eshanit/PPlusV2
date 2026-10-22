<script setup lang="ts">
import { format } from 'date-fns'
import type { ISession, IRound, MentorshipPhase } from '~/interfaces/ISession'
import type { ITool } from '~/interfaces/ITool'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import {
  counsellingTool,
  MENTOR_LABELS,
  NA_LABEL,
  SCORE_LABELS,
  scoreBackground,
  scoreTextColor,
  toolIncludesCounselling,
} from '~/data/evaluationItemData'
import { getCompetencyBreakdown, getLatestItemScores } from '~/composables/useCompetency'

/**
 * Paper-style view of a journey: competencies down the side, sessions across
 * the top, like the PEN-Plus tool's Date 1…Date 5 columns. Each cell is the
 * session's latest score for the item (its last round that rated it).
 */
const props = defineProps<{
  sessions: ISession[]
  tool: ITool | null | undefined
}>()

type Score = 1 | 2 | 3 | 4 | 5

const PHASE_LABELS: Record<MentorshipPhase, string> = {
  initial_intensive: 'Initial intensive',
  ongoing: 'Ongoing',
  supervision: 'Supervision',
}

function roundsOf(session: ISession): IRound[] {
  return session.rounds?.length
    ? session.rounds
    : [{
        roundId: `legacy-${session._id}`,
        itemScores: session.itemScores ?? [],
        counsellingScores: session.counsellingScores ?? [],
        startedAt: session.createdAt,
      }]
}

// Oldest first, the same order used for session numbers and competency.
const ordered = computed(() => [...props.sessions].sort((a, b) =>
  a.evalDate - b.evalDate
  || (a.roundOfDay ?? Number.POSITIVE_INFINITY) - (b.roundOfDay ?? Number.POSITIVE_INFINITY)
  || a.createdAt - b.createdAt))

const columns = computed(() => ordered.value.map((session, index) => ({
  key: session._id,
  number: index + 1,
  date: format(new Date(session.evalDate), 'd MMM yyyy'),
  phase: session.phase ? PHASE_LABELS[session.phase] : '—',
  rounds: roundsOf(session).length,
  reopened: !!session.reopenReason,
})))

interface CellValue {
  /** 1-5, null for N/A, undefined when the session didn't evaluate the item. */
  score: Score | null | undefined
  note?: string
}

// What each session recorded for an item: its latest non-N/A score across the
// session's rounds, else N/A if it was only marked N/A, else nothing.
function sessionValue(session: ISession, slug: string, kind: 'tool' | 'counselling'): CellValue {
  let score: Score | null | undefined
  let note: string | undefined
  for (const round of roundsOf(session)) {
    const list = kind === 'tool' ? round.itemScores : round.counsellingScores
    const entry = (list ?? []).find(s => s.itemSlug === slug)
    if (!entry) continue
    if (entry.menteeScore !== null) score = entry.menteeScore
    else if (score === undefined) score = null
    if (entry.notes?.trim()) note = entry.notes.trim()
  }
  return { score, note }
}

const breakdown = computed(() => getCompetencyBreakdown(props.sessions, props.tool))
const latestScores = computed(() => getLatestItemScores(props.sessions))

const selected = ref<{ slug: string; column: number } | null>(null)

function cellStyle(score: Score | null | undefined) {
  if (score === undefined) return undefined
  return { backgroundColor: scoreBackground(score), color: scoreTextColor(score) }
}

function buildRow(item: IEvalItem, kind: 'tool' | 'counselling') {
  const cells = ordered.value.map((session, column) => {
    const value = sessionValue(session, item.slug, kind)
    const isSelected = selected.value?.slug === item.slug && selected.value.column === column
    return {
      column,
      ...value,
      label: value.score === undefined ? '–' : value.score === null ? 'N/A' : String(value.score),
      style: cellStyle(value.score),
      isSelected,
      aria: `${item.number}, session ${column + 1}: ${value.score === undefined ? 'not scored' : value.score === null ? 'N/A' : `score ${value.score}`}`,
    }
  })

  // Latest recorded score in the journey (counselling: latest across sessions).
  let latest: number | undefined
  if (kind === 'tool') {
    latest = latestScores.value.get(item.slug)?.score
  } else {
    for (const cell of [...cells].reverse()) {
      if (typeof cell.score === 'number') { latest = cell.score; break }
    }
  }

  const achieved = latest !== undefined && latest >= 4
  let status: { label: string; tone: 'achieved' | 'pending' | 'neutral' }
  if (kind === 'counselling') status = { label: 'Not counted', tone: 'neutral' }
  else if (achieved) status = { label: 'Achieved', tone: 'achieved' }
  else if (item.isAdvanced) status = { label: 'Not required', tone: 'neutral' }
  else status = { label: 'Not yet', tone: 'pending' }

  return {
    item,
    kind,
    cells,
    latest,
    latestStyle: latest === undefined ? undefined : cellStyle(latest as Score),
    status,
  }
}

const sections = computed(() => {
  if (!props.tool) return []
  const bySlug = new Map(props.tool.items.map(item => [item.slug, item]))
  const groups = props.tool.groups?.length
    ? props.tool.groups.map(group => ({
        key: group.slug,
        label: group.label,
        items: group.itemSlugs.map(slug => bySlug.get(slug)).filter((item): item is IEvalItem => !!item),
      }))
    : [{ key: 'all', label: props.tool.label, items: props.tool.items }]

  const result = groups.map(group => ({
    key: group.key,
    label: group.label,
    counselling: false,
    rows: group.items.map(item => buildRow(item, 'tool')),
  }))

  // Counselling items are shown for reference only; they don't count towards competency.
  const anyCounselling = ordered.value.some(session =>
    roundsOf(session).some(round => (round.counsellingScores ?? []).length > 0))
  if (toolIncludesCounselling(props.tool) || anyCounselling) {
    result.push({
      key: 'counselling',
      label: 'General Counselling Competencies',
      counselling: true,
      rows: counsellingTool.items.map(item => buildRow(item, 'counselling')),
    })
  }
  return result
})

const progressPct = computed(() => breakdown.value.requiredCount
  ? Math.round((breakdown.value.requiredAchieved / breakdown.value.requiredCount) * 100)
  : 0)

const detail = computed(() => {
  if (!selected.value) return null
  for (const section of sections.value) {
    const row = section.rows.find(r => r.item.slug === selected.value?.slug)
    if (!row) continue
    const cell = row.cells[selected.value.column]
    const column = columns.value[selected.value.column]
    if (!cell || !column || cell.score === undefined) return null
    return {
      code: row.item.number,
      title: row.item.title,
      session: column.number,
      date: column.date,
      label: cell.label,
      style: cell.style,
      mentee: cell.score === null ? NA_LABEL : SCORE_LABELS[cell.score],
      mentor: cell.score === null ? undefined : MENTOR_LABELS[cell.score],
      note: cell.note,
    }
  }
  return null
})

function pick(slug: string, column: number) {
  selected.value = { slug, column }
}

const legend = [
  ...([1, 2, 3, 4, 5] as const).map(score => ({
    key: String(score),
    label: String(score),
    name: ['Not demonstrated', 'Basic', 'Satisfactory', 'Advanced', 'Exceptional'][score - 1],
    style: cellStyle(score),
    dashed: false,
  })),
  { key: 'na', label: 'N/A', name: 'Not evaluated', style: cellStyle(null), dashed: false },
  { key: 'none', label: '–', name: 'Not scored that session', style: undefined, dashed: true },
]
</script>

<template>
  <div class="space-y-4">
    <!-- Progress against the competency rule -->
    <section class="flex flex-wrap items-center justify-between gap-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <div class="min-w-0 flex-[1_1_22rem]">
        <p class="text-lg font-bold text-gray-900 dark:text-white">
          {{ breakdown.requiredAchieved }} of {{ breakdown.requiredCount }} required items achieved
        </p>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
          An item is achieved once its latest score is 4 or 5. The mentee reaches Basic Competent when every required item is achieved. Advanced items are not required.
        </p>
        <div
          class="mt-3 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
          role="img"
          :aria-label="`${breakdown.requiredAchieved} of ${breakdown.requiredCount} required items achieved`"
        >
          <div class="h-full rounded-full bg-green-600" :style="{ width: `${progressPct}%` }" />
        </div>
      </div>
      <ul class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-700 dark:text-gray-300">
        <li v-for="entry in legend" :key="entry.key" class="flex items-center gap-1.5">
          <span
            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md px-1 text-xs font-bold"
            :class="entry.dashed ? 'border-2 border-dashed border-gray-400 text-gray-500' : ''"
            :style="entry.style"
          >{{ entry.label }}</span>
          {{ entry.name }}
        </li>
      </ul>
    </section>

    <!-- The grid -->
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <p class="px-4 pt-3 text-sm text-gray-600 dark:text-gray-300">
        Tap a score to see what it means for the mentee and the mentor.
      </p>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[44rem] border-collapse text-sm">
          <thead>
            <tr class="bg-gray-50 dark:bg-gray-800">
              <th scope="col" class="sticky left-0 z-10 min-w-[16rem] border-b border-gray-200 bg-gray-50 px-4 py-3 text-left font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                Competency
              </th>
              <th
                v-for="column in columns"
                :key="column.key"
                scope="col"
                class="min-w-[6.5rem] border-b border-gray-200 px-2 py-2 text-center align-bottom dark:border-gray-700"
              >
                <span class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Session {{ column.number }}</span>
                <span class="block font-bold text-gray-900 dark:text-white">{{ column.date }}</span>
                <span class="block text-xs text-gray-600 dark:text-gray-300">
                  {{ column.phase }}<template v-if="column.rounds > 1"> · {{ column.rounds }} rounds</template>
                </span>
                <span
                  v-if="column.reopened"
                  class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-900 dark:bg-amber-900/50 dark:text-amber-100"
                >Reopened</span>
              </th>
              <th scope="col" class="min-w-[5.5rem] border-b border-l-2 border-gray-200 px-2 py-2 text-center font-semibold text-gray-900 dark:border-gray-700 dark:text-white">
                Latest
              </th>
              <th scope="col" class="min-w-[8rem] border-b border-gray-200 px-3 py-2 text-left font-semibold text-gray-900 dark:border-gray-700 dark:text-white">
                Status
              </th>
            </tr>
          </thead>
          <tbody v-for="section in sections" :key="section.key">
            <tr>
              <th
                scope="colgroup"
                :colspan="columns.length + 3"
                class="border-y border-amber-200 bg-amber-50 px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
              >
                {{ section.label }}
                <span v-if="section.counselling" class="ml-1 font-semibold normal-case tracking-normal">(not counted towards competency)</span>
              </th>
            </tr>
            <tr
              v-for="row in section.rows"
              :key="row.item.slug"
              :class="row.item.isAdvanced ? 'bg-gray-100 dark:bg-gray-800' : 'bg-white dark:bg-gray-900'"
            >
              <th
                scope="row"
                class="sticky left-0 z-10 border-b border-gray-100 px-4 py-2 text-left align-top font-normal dark:border-gray-800"
                :class="row.item.isAdvanced ? 'bg-gray-100 dark:bg-gray-800' : 'bg-white dark:bg-gray-900'"
              >
                <span class="flex items-start gap-2.5">
                  <span
                    class="min-w-10 shrink-0 rounded-md px-1.5 py-0.5 text-center text-sm font-bold"
                    :class="row.item.isAdvanced ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100'"
                  >{{ row.item.number }}</span>
                  <span class="leading-snug text-gray-900 dark:text-gray-100">
                    {{ row.item.title }}
                    <span
                      v-if="row.item.isAdvanced"
                      class="ml-1 inline-block rounded-full bg-gray-700 px-2 py-px align-middle text-[0.6875rem] font-bold uppercase tracking-wide text-white"
                    >Advanced</span>
                  </span>
                </span>
              </th>
              <td
                v-for="cell in row.cells"
                :key="cell.column"
                class="border-b border-gray-100 px-2 py-1.5 text-center dark:border-gray-800"
              >
                <button
                  v-if="cell.score !== undefined"
                  type="button"
                  class="h-11 w-12 rounded-lg border-2 text-base font-bold focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                  :class="cell.isSelected ? 'border-gray-900 ring-2 ring-gray-900/25 dark:border-white' : 'border-transparent'"
                  :style="cell.style"
                  :aria-label="cell.aria"
                  :aria-pressed="cell.isSelected"
                  @click="pick(row.item.slug, cell.column)"
                >
                  {{ cell.label }}
                </button>
                <span
                  v-else
                  class="inline-flex h-11 w-12 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-gray-400 dark:border-gray-600"
                  :aria-label="cell.aria"
                >–</span>
              </td>
              <td class="border-b border-l-2 border-gray-100 border-l-gray-200 px-2 py-1.5 text-center dark:border-gray-800 dark:border-l-gray-700">
                <span
                  v-if="row.latest !== undefined"
                  class="inline-flex h-11 w-12 items-center justify-center rounded-lg text-base font-bold"
                  :style="row.latestStyle"
                >{{ row.latest }}</span>
                <span v-else class="text-gray-400">–</span>
              </td>
              <td class="border-b border-gray-100 px-3 py-1.5 dark:border-gray-800">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-sm font-bold"
                  :class="{
                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200': row.status.tone === 'achieved',
                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200': row.status.tone === 'pending',
                    'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200': row.status.tone === 'neutral',
                  }"
                >
                  <UIcon v-if="row.status.tone === 'achieved'" name="i-heroicons-check" class="size-4" />
                  {{ row.status.label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Meaning of the tapped score -->
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-live="polite">
      <div v-if="detail" class="flex flex-wrap items-start gap-4">
        <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-xl text-xl font-bold" :style="detail.style">
          {{ detail.label }}
        </span>
        <div class="min-w-0 flex-[1_1_18rem]">
          <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">
            {{ detail.code }} · Session {{ detail.session }} · {{ detail.date }}
          </p>
          <p class="mb-2 mt-0.5 text-base font-semibold leading-snug text-gray-900 dark:text-white">{{ detail.title }}</p>
          <dl class="grid grid-cols-[max-content_1fr] gap-x-3 gap-y-1 text-base">
            <dt class="flex items-center font-bold text-gray-700 dark:text-gray-200">
              Mentee<UIcon name="i-heroicons-arrow-right" class="mx-1 size-4" aria-hidden="true" />
            </dt>
            <dd class="text-gray-900 dark:text-gray-100">{{ detail.mentee }}</dd>
            <template v-if="detail.mentor">
              <dt class="flex items-center font-bold text-gray-700 dark:text-gray-200">
                Mentor<UIcon name="i-heroicons-arrow-right" class="mx-1 size-4" aria-hidden="true" />
              </dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ detail.mentor }}</dd>
            </template>
            <template v-if="detail.note">
              <dt class="font-bold text-gray-700 dark:text-gray-200">Note</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ detail.note }}</dd>
            </template>
          </dl>
        </div>
      </div>
      <p v-else class="text-sm text-gray-600 dark:text-gray-300">
        Tap any score in the grid to see its meaning for the mentee and the mentor.
      </p>
    </section>
  </div>
</template>
