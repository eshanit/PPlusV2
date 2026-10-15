<script setup lang="ts">
import { format } from 'date-fns'
import { useSessionStore } from '~/stores/sessionStore'
import { useUserStore } from '~/stores/userStore'
import { getToolBySlug, counsellingTool } from '~/data/evaluationItemData'
import type { IRound, ISession, MentorshipPhase } from '~/interfaces/ISession'
import { calculateScoreCounts, calculateAverage, formatDate } from '~/composables/useSessionCalculations'
import ScoreLegend from '~/components/session/ScoreLegend.vue'
import ScoreDistribution from '~/components/session/ScoreDistribution.vue'
import ItemScoresList from '~/components/session/ItemScoresList.vue'

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

const averageScore = computed(() => {
  if (!previewData.value) return '0'
  const items = previewData.value.items.map(i => ({ itemSlug: i.slug, menteeScore: i.score, notes: i.notes }))
  return calculateAverage(items)
})

const naCount = computed(() => {
  if (!previewData.value) return 0
  return previewData.value.items.filter(item => item.evaluated && item.score === null).length
})

const toolAverageScore = computed(() => {
  if (!previewData.value) return '0'
  const items = previewData.value.items.filter(i => i.type === 'tool').map(i => ({ itemSlug: i.slug, menteeScore: i.score, notes: i.notes }))
  return calculateAverage(items)
})

const dcAverageScore = computed(() => {
  if (!previewData.value) return '0'
  const items = previewData.value.items.filter(i => i.type === 'counselling').map(i => ({ itemSlug: i.slug, menteeScore: i.score, notes: i.notes }))
  return calculateAverage(items)
})

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
  }))
})

const toolItemMetadata = computed(() => allItemMetadata.value)

// Tool items mapped to ItemScore format
const toolItemsData = computed(() => {
  if (!previewData.value) return []
  return previewData.value.items
    .filter(i => i.type === 'tool' && (i.evaluated ?? true))
    .map(item => ({
      itemSlug: item.slug,
      menteeScore: item.score,
      notes: item.notes,
    }))
})

// Counselling items mapped to ItemScore format
const counsellingItemsData = computed(() => {
  if (!previewData.value) return []
  return previewData.value.items
    .filter(i => i.type === 'counselling' && (i.evaluated ?? true))
    .map(item => ({
      itemSlug: item.slug,
      menteeScore: item.score,
      notes: item.notes,
    }))
})

const counsellingItemMetadata = computed(() =>
  counsellingTool.items.map(item => ({ slug: item.slug, number: item.number, title: item.title }))
)

const reviewRoundGroups = computed(() => {
  if (!previewData.value?.rounds?.length || !tool.value) return []
  return previewData.value.rounds.map((round, index) => {
    const toolGroups = (tool.value?.groups ?? []).map(group => ({
      key: group.slug,
      title: group.label,
      items: round.itemScores.filter(score => group.itemSlugs.includes(score.itemSlug)),
      metadata: group.itemSlugs.flatMap(slug => {
        const item = tool.value?.items.find(candidate => candidate.slug === slug)
        return item ? [{ slug: item.slug, number: item.number, title: item.title }] : []
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
</script>

<template>
  <div>
    <!-- Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-14 z-30">
      <div class="max-w-4xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div>
              <h1 class="font-semibold text-gray-900 dark:text-white">Evaluation Overview</h1>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ previewData?.menteeName }} · {{ previewData?.toolLabel }}
              </p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-xs text-gray-500 dark:text-gray-400">Progress</p>
            <p class="text-sm font-medium text-gray-900 dark:text-white">
              {{ totalScored }}/{{ totalItems }} scored
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ previewData?.rounds?.length ?? 1 }} round(s)
            </p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="previewData" class="max-w-4xl mx-auto px-4 py-4 space-y-6">
      <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        Plan: {{ plannedItemCount }} tool items · {{ plannedCounsellingCount }} counselling items
      </div>

      <!-- Reopened journey -->
      <div
        v-if="previewData.reopenReason"
        class="flex items-start gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-3 py-2.5 text-amber-800 dark:text-amber-300"
      >
        <UIcon name="i-heroicons-lock-open" class="w-4 h-4 mt-0.5 shrink-0" />
        <div class="text-sm">
          <p class="font-medium">This session reopens a closed journey.</p>
          <p class="text-xs mt-0.5 opacity-80">Reason: {{ previewData.reopenReason }}</p>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-3 gap-3">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ totalScored }}</p>
          <p class="text-xs text-blue-600 dark:text-blue-400">Scored</p>
        </div>
        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ naCount }}</p>
          <p class="text-xs text-orange-600 dark:text-orange-400">N/A</p>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ averageScore }}</p>
          <p class="text-xs text-green-600 dark:text-green-400">Overall Avg</p>
        </div>
      </div>

      <!-- Tool and DC Averages -->
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-3 text-center">
          <p class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ toolAverageScore }}</p>
          <p class="text-xs text-primary-600 dark:text-primary-400">{{ previewData.toolLabel }} Avg</p>
        </div>
        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 text-center">
          <p class="text-xl font-bold text-orange-600 dark:text-orange-400">{{ dcAverageScore }}</p>
          <p class="text-xs text-orange-600 dark:text-orange-400">Counselling Avg</p>
        </div>
      </div>

      <section
        v-if="previewData.toolSlug === 'echo'"
        class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950/30"
      >
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Echocardiogram activity</h2>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
          Visit-level supervised activity counts. These are separate from ratings and do not affect competency status.
        </p>
        <div class="mt-3 grid grid-cols-2 gap-3 text-center">
          <div class="rounded-lg bg-white/70 p-3 dark:bg-gray-900/50">
            <p class="text-xl font-bold text-sky-800 dark:text-sky-200">{{ previewData.echocardiogramsPerformed ?? 'Not recorded' }}</p>
            <p class="text-xs text-gray-600 dark:text-gray-300">Performed under supervision</p>
          </div>
          <div class="rounded-lg bg-white/70 p-3 dark:bg-gray-900/50">
            <p class="text-xl font-bold text-sky-800 dark:text-sky-200">{{ previewData.echocardiogramsCorrectDiagnosis ?? 'Not recorded' }}</p>
            <p class="text-xs text-gray-600 dark:text-gray-300">Correct final diagnosis</p>
          </div>
        </div>
        <p v-if="echoActivityError" class="mt-2 text-xs font-medium text-red-700 dark:text-red-300" role="alert">
          {{ echoActivityError }}
        </p>
      </section>

      <!-- Session Details -->
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1">
              Evaluation Date
            </label>
            <UInput type="date" v-model="evalDateStr" :min="latestJourneyDateStr" class="w-full" />
            <p v-if="dateBeforeReopenedJourney" class="text-xs text-red-500 mt-1">
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

      <!-- Score Legend -->
      <ScoreLegend />

      <!-- Score Distribution -->
      <ScoreDistribution :counts="scoreCounts" />

      <section v-if="reviewRoundGroups.length" class="space-y-4">
        <div>
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Round-by-round review</h2>
          <div class="mt-2 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
            <UIcon name="i-heroicons-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>
              Each section shows the scores recorded in that observation round only. Compare rounds to see how observations differed during this visit.
            </p>
          </div>
        </div>
        <article
          v-for="round in reviewRoundGroups"
          :key="round.key"
          class="space-y-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-950"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold text-gray-900 dark:text-white">{{ round.label }}</h3>
              <p v-if="round.notes" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ round.notes }}</p>
            </div>
            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
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
      </section>

      <!-- Latest non-null score per planned item -->
      <div class="flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
        <UIcon name="i-heroicons-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
        <p>
          The session summary combines all rounds from this visit. For each competency, it shows the most recent score recorded; competencies not scored in any round have no score here.
        </p>
      </div>
      <ItemScoresList
        :title="`${previewData.toolLabel} · session summary`"
        :items="toolItemsData"
        :item-metadata="toolItemMetadata"
      />

      <ItemScoresList
        title="General Counselling Competencies · session summary"
        :items="counsellingItemsData"
        :item-metadata="counsellingItemMetadata"
        color-class="bg-orange-50 dark:bg-orange-900/20 border-orange-100 dark:border-orange-800"
      />

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

      <!-- Save Button -->
      <div class="pt-4 pb-8">
        <UButton
          color="success"
          block
          size="xl"
          :loading="saving"
          :disabled="!isValid"
          @click="saveSession"
        >
          <UIcon name="i-heroicons-check" class="w-5 h-5 mr-2" />
          Save Session
        </UButton>
      </div>
    </div>
  </div>
</template>