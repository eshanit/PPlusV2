<script setup lang="ts">
import { format } from 'date-fns'
import { useSessionStore } from '~/stores/sessionStore'
import { useUserStore } from '~/stores/userStore'
import {
  getToolBySlug,
  counsellingTool,
  scoreBand,
  scoreBackground,
  scoreTextColor,
  SCORE_SHORT_LABELS,
} from '~/data/evaluationItemData'
import type { IRound, ISession, MentorshipPhase } from '~/interfaces/ISession'
import { calculateScoreCounts, averageValue } from '~/composables/useSessionCalculations'
import { getCompetencyBreakdown, getCompetencyStatus } from '~/composables/useCompetency'
import ScoreDistribution from '~/components/session/ScoreDistribution.vue'
import ItemScoresList from '~/components/session/ItemScoresList.vue'
import ScoreChip from '~/components/session/ScoreChip.vue'

const router = useRouter()
const toast = useToast()
const sessionStore = useSessionStore()
const userStore = useUserStore()

const saving = ref(false)
const evalDateStr = ref(new Date().toISOString().split('T')[0])
const phase = ref<string | undefined>(undefined)
const notes = ref('')

interface PreviewItem {
  slug: string
  number: string
  title: string
  category: string
  type: 'tool' | 'counselling'
  score: number | null
  notes: string
  evaluated?: boolean
}

interface PreviewData {
  menteeId: string
  menteeName: string
  toolSlug: string
  toolLabel: string
  items: PreviewItem[]
  rounds?: IRound[]
  selectedItemSlugs?: string[]
  selectedCounsellingSlugs?: string[]
  echocardiogramsPerformed?: number
  echocardiogramsCorrectDiagnosis?: number
  sessionId?: string
  sessionRev?: string
  phase: MentorshipPhase | null
  evalDate: string
  reopenReason?: string
  notes: string
}

const previewData = ref<PreviewData | null>(null)

onMounted(async () => {
  await userStore.loadUsers()
  const stored = sessionStorage.getItem('penplus_preview')
  if (stored) {
    try {
      const data = JSON.parse(stored) as PreviewData
      previewData.value = data
      evalDateStr.value = data.evalDate
      phase.value = data.phase ?? undefined
      notes.value = data.notes
      sessionStorage.removeItem('penplus_preview')
    } catch {
      router.back()
    }
  } else {
    router.back()
  }
})

const totalScored = computed(() => {
  if (!previewData.value) return 0
  return previewData.value.items.filter(item => item.score !== null).length
})

const totalItems = computed(() => {
  if (!previewData.value) return 0
  return previewData.value.items.length
})

const plannedItemCount = computed(() => previewData.value?.selectedItemSlugs?.length
  ?? previewData.value?.items.filter(item => item.type === 'tool').length
  ?? 0)
const plannedCounsellingCount = computed(() => previewData.value?.selectedCounsellingSlugs?.length
  ?? previewData.value?.items.filter(item => item.type === 'counselling').length
  ?? 0)

const notScoredCount = computed(() =>
  previewData.value?.items.filter(item => item.evaluated === false).length ?? 0)

const naCount = computed(() => {
  if (!previewData.value) return 0
  return previewData.value.items.filter(item => item.evaluated && item.score === null).length
})

function averageFor(type: PreviewItem['type']): number | null {
  if (!previewData.value) return null
  return averageValue(previewData.value.items
    .filter(i => i.type === type)
    .map(i => ({ itemSlug: i.slug, menteeScore: i.score })))
}

const averageTiles = computed(() => [
  { key: 'tool', label: `${previewData.value?.toolLabel ?? 'Tool'} average`, value: averageFor('tool') },
  { key: 'counselling', label: 'Counselling average', value: averageFor('counselling') },
].map(tile => {
  const band = scoreBand(tile.value)
  return {
    ...tile,
    band,
    bandLabel: band ? SCORE_SHORT_LABELS[band] : 'No scores yet',
    style: band ? { backgroundColor: scoreBackground(band), color: scoreTextColor(band) } : undefined,
  }
}))

const toolScoresForDistribution = computed(() => {
  if (!previewData.value) return []
  return previewData.value.items
    .filter(i => i.type === 'tool' && (i.evaluated ?? true))
    .map(item => ({ itemSlug: item.slug, menteeScore: item.score, notes: item.notes }))
})

const scoreCounts = computed(() => calculateScoreCounts(toolScoresForDistribution.value))

async function saveSession() {
  if (!previewData.value || !userStore.currentUser) return
  saving.value = true
  const pData = previewData.value
  try {
    const mentee = userStore.allUsers.find(u => u.id === pData.menteeId)
    const tool = getToolBySlug(pData.toolSlug)
    if (!mentee || !tool) {
      throw new Error('Invalid session data')
    }
    const now = Date.now()
    const evalDate = new Date(evalDateStr.value + 'T12:00:00').getTime()
    const evaluationGroupId = `${pData.menteeId}::${tool.slug}`

    const toolItems = pData.items.filter(i => i.type === 'tool' && (i.evaluated ?? true))
    const counsellingItems = pData.items.filter(i => i.type === 'counselling' && (i.evaluated ?? true))
    const rounds: IRound[] = pData.rounds?.length
      ? pData.rounds
      : [{
          roundId: `legacy-${now}`,
          label: 'Round 1',
          itemScores: toolItems.map(item => ({
            itemSlug: item.slug,
            menteeScore: (item.score ?? null) as 1 | 2 | 3 | 4 | 5 | null,
            notes: item.notes?.trim() || undefined,
          })),
          counsellingScores: counsellingItems.map(item => ({
            itemSlug: item.slug,
            menteeScore: (item.score ?? null) as 1 | 2 | 3 | 4 | 5 | null,
            notes: item.notes?.trim() || undefined,
          })),
          startedAt: now,
          completedAt: now,
        }]
    const existingSession = pData.sessionId
      ? sessionStore.sessions.find(session => session._id === pData.sessionId)
      : undefined

    await sessionStore.save({
      _id: pData.sessionId ?? '',
      _rev: pData.sessionRev ?? existingSession?._rev,
      type: 'session',
      evaluationGroupId,
      mentee: {
        id: mentee.id,
        firstname: mentee.firstname,
        lastname: mentee.lastname,
        username: mentee.username ?? '',
        facilityId: mentee.facilityId,
        districtId: mentee.districtId,
      },
      evaluator: userStore.currentUser,
      toolSlug: tool.slug,
      evalDate,
      reopenReason: pData.reopenReason,
      facilityId: mentee.facilityId ?? '',
      districtId: mentee.districtId ?? '',
      itemScores: toolItems.map(item => ({
        itemSlug: item.slug,
        menteeScore: (item.score ?? null) as 1 | 2 | 3 | 4 | 5 | null,
        notes: item.notes?.trim() || undefined,
      })),
      counsellingScores: counsellingItems.map(item => ({
        itemSlug: item.slug,
        menteeScore: (item.score ?? null) as 1 | 2 | 3 | 4 | 5 | null,
        notes: item.notes?.trim() || undefined,
      })),
      rounds,
      selectedItemSlugs: pData.selectedItemSlugs ?? toolItems.map(item => item.slug),
      selectedCounsellingSlugs: pData.selectedCounsellingSlugs ?? counsellingItems.map(item => item.slug),
      ...(pData.toolSlug === 'echo'
        ? {
            echocardiogramsPerformed: pData.echocardiogramsPerformed,
            echocardiogramsCorrectDiagnosis: pData.echocardiogramsCorrectDiagnosis,
          }
        : {}),
      phase: (phase.value ?? null) as MentorshipPhase | null,
      notes: notes.value.trim() || undefined,
      syncStatus: 'pending',
      createdAt: existingSession?.createdAt ?? now,
      updatedAt: now,
    })

    sessionStorage.removeItem('penplus_session_draft')
    toast.add({ title: 'Session saved', color: 'success', icon: 'i-heroicons-check-circle' })
    router.push(`/mentees/${pData.menteeId}`)
  } catch (err) {
    toast.add({ title: 'Save failed', description: String(err), color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    saving.value = false
  }
}

const tool = computed(() => {
  if (!previewData.value) return null
  return getToolBySlug(previewData.value.toolSlug)
})

const allItemMetadata = computed(() => {
  if (!tool.value) return []
  return tool.value.items.map(item => ({
    slug: item.slug,
    number: item.number,
    title: item.title,
    isAdvanced: item.isAdvanced,
  }))
})

function toListItem(item: PreviewItem) {
  return {
    itemSlug: item.slug,
    menteeScore: item.score,
    notes: item.notes,
    evaluated: item.evaluated ?? true,
  }
}

// Session summary, grouped into the tool's sections. Planned items that were
// never scored are included so the mentor can spot them before saving.
const toolSummaryGroups = computed(() => {
  if (!previewData.value || !tool.value) return []
  const toolItems = previewData.value.items.filter(item => item.type === 'tool')
  return (tool.value.groups ?? []).map(group => ({
    key: group.slug,
    title: group.label,
    items: toolItems.filter(item => group.itemSlugs.includes(item.slug)).map(toListItem),
  })).filter(group => group.items.length > 0)
})

const counsellingItemsData = computed(() => {
  if (!previewData.value) return []
  return previewData.value.items.filter(i => i.type === 'counselling').map(toListItem)
})

const counsellingItemMetadata = computed(() =>
  counsellingTool.items.map(item => ({ slug: item.slug, number: item.number, title: item.title, isAdvanced: item.isAdvanced }))
)

// Journey context: previous scores and competency outcome

const groupId = computed(() => previewData.value
  ? `${previewData.value.menteeId}::${previewData.value.toolSlug}`
  : '')

const otherSessions = computed(() => groupId.value
  ? sessionStore.sessionsForGroup(groupId.value).filter(s => s._id !== previewData.value?.sessionId)
  : [])

const thisCreatedAt = computed(() =>
  sessionStore.sessions.find(s => s._id === previewData.value?.sessionId)?.createdAt ?? Date.now())

const thisEvalDate = computed(() => new Date(`${evalDateStr.value}T12:00:00`).getTime())

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

// Most recent earlier score for each item, matching the session form's
// "Previous score". N/A counts as a previous entry but shows no change.
const previousScores = computed<Record<string, number | null>>(() => {
  const earlier = otherSessions.value
    .filter(s => s.evalDate < thisEvalDate.value
      || (s.evalDate === thisEvalDate.value && s.createdAt < thisCreatedAt.value))
    .sort((a, b) => b.evalDate - a.evalDate || b.createdAt - a.createdAt)
  const result: Record<string, number | null> = {}
  for (const session of earlier) {
    for (const round of [...roundsOf(session)].reverse()) {
      for (const score of [...round.itemScores, ...round.counsellingScores]) {
        if (!(score.itemSlug in result)) result[score.itemSlug] = score.menteeScore
      }
    }
  }
  return result
})

// This session as it would be saved, so the competency rule can be applied
// before the mentor commits.
const draftSession = computed<ISession | null>(() => {
  const p = previewData.value
  if (!p) return null
  const toolItems = p.items.filter(i => i.type === 'tool' && (i.evaluated ?? true))
  const itemScores = toolItems.map(item => ({
    itemSlug: item.slug,
    menteeScore: (item.score ?? null) as 1 | 2 | 3 | 4 | 5 | null,
  }))
  return {
    _id: p.sessionId ?? '__review__',
    type: 'session',
    evaluationGroupId: groupId.value,
    toolSlug: p.toolSlug,
    evalDate: thisEvalDate.value,
    itemScores,
    counsellingScores: [],
    rounds: p.rounds?.length
      ? p.rounds
      : [{ roundId: 'review', itemScores, counsellingScores: [], startedAt: thisCreatedAt.value }],
    selectedItemSlugs: p.selectedItemSlugs ?? toolItems.map(item => item.slug),
    createdAt: thisCreatedAt.value,
    updatedAt: thisCreatedAt.value,
  } as unknown as ISession
})

const STATUS_LABELS = { basic_competent: 'Basic Competent', fully_competent: 'Fully Competent' } as const

const statusBefore = computed(() => getCompetencyStatus(otherSessions.value, tool.value))
const breakdownAfter = computed(() => draftSession.value
  ? getCompetencyBreakdown([...otherSessions.value, draftSession.value], tool.value)
  : null)

const outcome = computed(() => {
  const after = breakdownAfter.value
  if (!after || after.requiredCount === 0) return null

  if (after.status === 'in_progress') {
    const count = after.basicBelow.length
    return {
      competent: false,
      title: 'Not yet competent',
      detail: `${after.requiredAchieved} of ${after.requiredCount} required items achieved. ${count} still need${count === 1 ? 's' : ''} a score of 4 or 5. Items already achieved in earlier sessions stay achieved.`,
      itemsHeading: 'Not yet achieved',
      items: after.basicBelow,
    }
  }

  const label = STATUS_LABELS[after.status]
  const unchanged = statusBefore.value === after.status && !previewData.value?.reopenReason
  const advancedCount = after.advancedBelow.length
  return {
    competent: true,
    title: unchanged ? `Journey stays ${label}` : `Saving will mark this journey ${label}`,
    detail: after.status === 'fully_competent'
      ? 'Every item in the tool, including advanced ones, has reached 4 or 5. The journey will close.'
      : `Every required item in the tool has reached 4 or 5, so the journey will close. ${advancedCount} advanced item${advancedCount === 1 ? ' has' : 's have'} not, which is not required.`,
    itemsHeading: advancedCount ? 'Advanced items not yet achieved (not required)' : '',
    items: after.advancedBelow,
  }
})

const OUTCOME_PREVIEW_COUNT = 6
const showAllOutcomeItems = ref(false)
const visibleOutcomeItems = computed(() => {
  const items = outcome.value?.items ?? []
  return showAllOutcomeItems.value ? items : items.slice(0, OUTCOME_PREVIEW_COUNT)
})

const reviewRoundGroups = computed(() => {
  if (!previewData.value?.rounds?.length || !tool.value) return []
  return previewData.value.rounds.map((round, index) => {
    const toolGroups = (tool.value?.groups ?? []).map(group => ({
      key: group.slug,
      title: group.label,
      items: round.itemScores.filter(score => group.itemSlugs.includes(score.itemSlug)),
      metadata: group.itemSlugs.flatMap(slug => {
        const item = tool.value?.items.find(candidate => candidate.slug === slug)
        return item ? [{ slug: item.slug, number: item.number, title: item.title, isAdvanced: item.isAdvanced }] : []
      }),
    })).filter(group => group.items.length > 0)

    const counsellingScores = round.counsellingScores
    if (counsellingScores.length) {
      toolGroups.push({
        key: 'counselling',
        title: 'General Counselling Competencies (SME)',
        items: counsellingScores,
        metadata: counsellingItemMetadata.value,
      })
    }

    return {
      key: round.roundId,
      label: round.label || `Round ${index + 1}`,
      notes: round.notes,
      groups: toolGroups,
    }
  })
})

// Same rule as the session form: a reopening session can't be backdated before
// the session it corrects, or its lower scores wouldn't count as the latest.
const latestJourneyDateStr = computed(() => {
  const p = previewData.value
  if (!p?.reopenReason) return undefined
  const groupId = `${p.menteeId}::${p.toolSlug}`
  const dates = sessionStore.sessions.filter(s => s.evaluationGroupId === groupId).map(s => s.evalDate)
  return dates.length ? format(new Date(Math.max(...dates)), 'yyyy-MM-dd') : undefined
})

const dateBeforeReopenedJourney = computed(() =>
  !!latestJourneyDateStr.value && !!evalDateStr.value && evalDateStr.value < latestJourneyDateStr.value
)

const echoActivityError = computed(() => {
  if (previewData.value?.toolSlug !== 'echo') return undefined
  const { echocardiogramsPerformed, echocardiogramsCorrectDiagnosis } = previewData.value
  if (
    echocardiogramsPerformed === undefined
    || echocardiogramsCorrectDiagnosis === undefined
    || !Number.isSafeInteger(echocardiogramsPerformed)
    || !Number.isSafeInteger(echocardiogramsCorrectDiagnosis)
    || echocardiogramsPerformed < 0
    || echocardiogramsCorrectDiagnosis < 0
  ) return 'Return to observation and enter valid whole-number echocardiogram counts.'
  if (echocardiogramsCorrectDiagnosis > echocardiogramsPerformed) {
    return 'The number with a correct final diagnosis cannot exceed the number performed.'
  }
  return undefined
})

const isValid = computed(() =>
  !!phase.value
  && !!evalDateStr.value
  && !dateBeforeReopenedJourney.value
  && !echoActivityError.value)

const saveBlockedReason = computed(() => {
  if (!evalDateStr.value) return 'Choose an evaluation date to save.'
  if (dateBeforeReopenedJourney.value) return 'The date is before the session being reopened.'
  if (!phase.value) return 'Select a mentorship phase to save.'
  if (echoActivityError.value) return 'Fix the echocardiogram counts to save.'
  return undefined
})

const roundCount = computed(() => previewData.value?.rounds?.length ?? 1)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-14 z-30">
      <div class="max-w-4xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <h1 class="font-semibold text-gray-900 dark:text-white">Review session</h1>
            <p class="truncate text-sm text-gray-500 dark:text-gray-400">
              {{ previewData?.menteeName }} · {{ previewData?.toolLabel }}
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
              {{ totalScored }} of {{ totalItems }} scored
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ roundCount }} round{{ roundCount === 1 ? '' : 's' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="previewData" class="max-w-4xl mx-auto px-4 py-4 space-y-6">
      <!-- Reopened journey -->
      <div
        v-if="previewData.reopenReason"
        class="flex items-start gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-3 py-2.5 text-amber-800 dark:text-amber-300"
      >
        <UIcon name="i-heroicons-lock-open" class="size-5 mt-0.5 shrink-0" />
        <div class="text-sm">
          <p class="font-semibold">This session reopens a closed journey.</p>
          <p class="mt-0.5">Reason: {{ previewData.reopenReason }}</p>
        </div>
      </div>

      <!-- Competency outcome -->
      <section
        v-if="outcome"
        class="rounded-xl border-2 p-4"
        :class="outcome.competent
          ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/30'
          : 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'"
        aria-live="polite"
      >
        <div class="flex items-start gap-3">
          <UIcon
            :name="outcome.competent ? 'i-heroicons-check-badge' : 'i-heroicons-clipboard-document-list'"
            class="size-7 shrink-0"
            :class="outcome.competent ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400'"
          />
          <div class="min-w-0 flex-1">
            <h2
              class="text-lg font-bold"
              :class="outcome.competent ? 'text-green-900 dark:text-green-100' : 'text-amber-900 dark:text-amber-100'"
            >
              {{ outcome.title }}
            </h2>
            <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ outcome.detail }}</p>
          </div>
        </div>

        <div v-if="outcome.items.length" class="mt-4">
          <p class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ outcome.itemsHeading }}</p>
          <ul class="space-y-1.5">
            <li
              v-for="entry in visibleOutcomeItems"
              :key="entry.item.slug"
              class="flex flex-col gap-1.5 rounded-lg bg-white/80 px-3 py-2 dark:bg-gray-900/60 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
            >
              <span class="flex min-w-0 items-start gap-2 text-sm text-gray-900 dark:text-gray-100">
                <span class="shrink-0 font-bold">{{ entry.item.number }}</span>
                <span class="min-w-0">{{ entry.item.title }}</span>
              </span>
              <ScoreChip :score="entry.latestScore" short size="sm" class="shrink-0" />
            </li>
          </ul>
          <UButton
            v-if="outcome.items.length > OUTCOME_PREVIEW_COUNT"
            variant="link"
            color="neutral"
            class="mt-2 px-0"
            @click="showAllOutcomeItems = !showAllOutcomeItems"
          >
            {{ showAllOutcomeItems ? 'Show fewer' : `Show all ${outcome.items.length}` }}
          </UButton>
        </div>
      </section>

      <!-- Summary -->
      <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalScored }}<span class="text-base font-semibold text-gray-500"> / {{ totalItems }}</span></p>
          <p class="text-sm text-gray-600 dark:text-gray-300">Items scored</p>
          <p v-if="notScoredCount" class="mt-1 text-sm font-semibold text-amber-700 dark:text-amber-400">
            {{ notScoredCount }} not scored
          </p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ naCount }}</p>
          <p class="text-sm text-gray-600 dark:text-gray-300">Marked N/A</p>
        </div>
        <div
          v-for="tile in averageTiles"
          :key="tile.key"
          class="rounded-lg p-3 text-center"
          :class="tile.band ? '' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
          :style="tile.style"
        >
          <p class="text-2xl font-bold">
            {{ tile.value === null ? '—' : tile.value.toFixed(1) }}<span class="text-base font-semibold"> / 5</span>
          </p>
          <p class="text-sm font-bold">{{ tile.bandLabel }}</p>
          <p class="mt-0.5 truncate text-xs font-medium opacity-90">{{ tile.label }}</p>
        </div>
      </section>

      <SessionEchoActivitySummary
        v-if="previewData.toolSlug === 'echo'"
        :performed="previewData.echocardiogramsPerformed"
        :correct="previewData.echocardiogramsCorrectDiagnosis"
        :error="echoActivityError"
      />

      <!-- Session Details -->
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1">
              Evaluation Date
            </label>
            <UInput type="date" v-model="evalDateStr" :min="latestJourneyDateStr" class="w-full" />
            <p v-if="dateBeforeReopenedJourney" class="text-sm text-red-600 mt-1">
              Can't be before {{ latestJourneyDateStr }}.
            </p>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1">
              Phase
            </label>
            <USelect
              v-model="phase"
              :items="[
                { label: 'Initial Intensive', value: 'initial_intensive' },
                { label: 'Ongoing', value: 'ongoing' },
                { label: 'Supervision', value: 'supervision' },
              ]"
              placeholder="Select phase"
              class="w-full"
            />
          </div>
        </div>
      </div>

      <!-- Score Distribution -->
      <ScoreDistribution :counts="scoreCounts" />

      <!-- Session summary -->
      <section class="space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Session summary</h2>
          <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
            <template v-if="roundCount > 1">Combines all rounds, showing the latest score for each item. </template>
            Changes are compared with the mentee's previous session.
          </p>
        </div>

        <ItemScoresList
          v-for="group in toolSummaryGroups"
          :key="group.key"
          :title="group.title"
          :items="group.items"
          :item-metadata="allItemMetadata"
          :previous-scores="previousScores"
        />

        <ItemScoresList
          v-if="counsellingItemsData.length"
          title="General Counselling Competencies"
          :items="counsellingItemsData"
          :item-metadata="counsellingItemMetadata"
          :previous-scores="previousScores"
          color-class="bg-orange-50 dark:bg-orange-900/20 border-orange-100 dark:border-orange-800"
        />
      </section>

      <!-- Round-by-round detail, only when there is more than one round -->
      <details
        v-if="roundCount > 1 && reviewRoundGroups.length"
        class="group rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
      >
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
          <span>
            <span class="font-semibold text-gray-900 dark:text-white">Round-by-round detail</span>
            <span class="block text-sm text-gray-500 dark:text-gray-400">Scores recorded in each of the {{ roundCount }} rounds</span>
          </span>
          <UIcon name="i-heroicons-chevron-right" class="size-5 shrink-0 text-gray-500 transition-transform group-open:rotate-90" />
        </summary>
        <div class="space-y-4 border-t border-gray-200 p-3 dark:border-gray-700">
          <article
            v-for="round in reviewRoundGroups"
            :key="round.key"
            class="space-y-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-950"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ round.label }}</h3>
                <p v-if="round.notes" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ round.notes }}</p>
              </div>
              <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">
                {{ round.groups.reduce((count, group) => count + group.items.length, 0) }} scored
              </span>
            </div>
            <ItemScoresList
              v-for="group in round.groups"
              :key="group.key"
              :title="group.title"
              :items="group.items"
              :item-metadata="group.metadata"
            />
          </article>
        </div>
      </details>

      <!-- Session Notes -->
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
          Session Notes <span class="text-gray-400 font-normal normal-case tracking-normal">(optional)</span>
        </label>
        <UTextarea
          v-model="notes"
          placeholder="Any observations or comments about this session…"
          :rows="3"
          class="w-full"
        />
      </div>

      <!-- Actions -->
      <div class="sticky bottom-2 z-20 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <UButton variant="soft" color="neutral" size="xl" icon="i-heroicons-arrow-left" @click="router.back()">
            Back to edit
          </UButton>
          <UButton
            color="success"
            size="xl"
            icon="i-heroicons-check"
            :loading="saving"
            :disabled="!isValid"
            @click="saveSession"
          >
            Save Session
          </UButton>
        </div>
        <p v-if="saveBlockedReason" class="mt-2 text-right text-sm font-medium text-amber-700 dark:text-amber-400" role="status">
          {{ saveBlockedReason }}
        </p>
      </div>
    </div>
  </div>
</template>
