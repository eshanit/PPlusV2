<script setup lang="ts">
import { format } from 'date-fns'
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { counsellingTool, getToolBySlug } from '~/data/evaluationItemData'
import { getCompetencyStatus } from '~/composables/useCompetency'
import type { IItemScore } from '~/interfaces/IItemScore'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import type { IRound, ISession, MentorshipPhase } from '~/interfaces/ISession'
import SessionScoreRow from '~/components/session/ScoreRow.vue'
import ScoreLegend from '~/components/session/ScoreLegend.vue'

definePageMeta({
  middleware: [(to) => {
    if (!to.query.menteeId || !to.query.toolSlug) {
      return navigateTo('/')
    }
  }],
})

type ScoreValue = 1 | 2 | 3 | 4 | 5 | null
type ItemKind = 'tool' | 'counselling'

interface DraftRound {
  roundId: string
  label: string
  scores: Record<string, ScoreValue>
  notes: Record<string, string>
  notesText: string
  startedAt: number
}

interface GroupItem extends IEvalItem {
  kind: ItemKind
}

interface ItemGroup {
  slug: string
  label: string
  kind: ItemKind
  items: GroupItem[]
}

interface PreviousAssessment {
  score: ScoreValue
  evalDate: number
  sessionNumber: number
  roundNumber: number
}

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const sessionStore = useSessionStore()
const toast = useToast()

await Promise.all([userStore.loadUsers(), sessionStore.loadAll()])

const menteeId = computed(() => String(route.query.menteeId ?? ''))
const toolSlug = computed(() => String(route.query.toolSlug ?? ''))
const editId = computed(() => typeof route.query.edit === 'string' ? route.query.edit : undefined)
const tool = computed(() => getToolBySlug(toolSlug.value))
const mentee = computed(() => userStore.allUsers.find(user => user.id === menteeId.value))
const menteeName = computed(() => mentee.value
  ? `${mentee.value.firstname} ${mentee.value.lastname}`
  : 'Unknown Mentee')
const evaluationGroupId = computed(() => mentee.value && tool.value
  ? `${mentee.value.id}::${tool.value.slug}`
  : '')
const previousSessions = computed(() =>
  sessionStore.sessionsForGroup(evaluationGroupId.value).filter(session => session._id !== editId.value))
const totalPreviousSessions = computed(() => previousSessions.value.length)
const reopenReason = computed(() => {
  const value = route.query.reopenReason
  return typeof value === 'string' && value.trim() ? value.trim() : undefined
})

const journeyClosed = computed(() => {
  const status = getCompetencyStatus(previousSessions.value, tool.value)
  return status === 'basic_competent' || status === 'fully_competent'
})

if (journeyClosed.value && !reopenReason.value && !editId.value) {
  toast.add({
    title: 'Journey closed',
    description: 'This mentee has reached competency on this tool. Reopen the journey from the mentee page to add a session.',
    color: 'warning',
    icon: 'i-heroicons-lock-closed',
  })
  await navigateTo(menteeId.value ? `/mentees/${menteeId.value}` : '/')
}

const latestJourneyDateStr = computed(() => {
  if (!reopenReason.value || previousSessions.value.length === 0) return undefined
  return format(new Date(Math.max(...previousSessions.value.map(session => session.evalDate))), 'yyyy-MM-dd')
})

const phase = ref<MentorshipPhase | null>(null)
const evalDateStr = ref(format(new Date(), 'yyyy-MM-dd'))
const sessionNotes = ref('')
const saving = ref(false)
const activeStep = ref<'plan' | 'observe'>('plan')
const activeRoundIndex = ref(0)
const selectedItemSlugs = ref<string[]>([])
const selectedCounsellingSlugs = ref(counsellingTool.items.map(item => item.slug))
const rounds = reactive<DraftRound[]>([createDraftRound(1)])

function compareSessions(a: ISession, b: ISession): number {
  return a.evalDate - b.evalDate
    || sessionStore.roundSortKey(a) - sessionStore.roundSortKey(b)
    || a.createdAt - b.createdAt
}

const sessionsBeforeCurrent = computed(() => {
  const sessions = sessionStore.sessionsForGroup(evaluationGroupId.value)
  const currentEvalDate = new Date(`${evalDateStr.value}T12:00:00`).getTime()

  if (editId.value) {
    const ordered = sessions
      .map(session => session._id === editId.value
        ? { ...session, evalDate: currentEvalDate }
        : session)
      .sort(compareSessions)
    const currentIndex = ordered.findIndex(session => session._id === editId.value)
    if (currentIndex >= 0) return ordered.slice(0, currentIndex)
  }

  const currentCreatedAt = Date.now()
  return sessions
    .filter(session => session.evalDate < currentEvalDate
      || (session.evalDate === currentEvalDate
        && (sessionStore.roundSortKey(session) < Number.POSITIVE_INFINITY
          || session.createdAt <= currentCreatedAt)))
    .sort(compareSessions)
})

const currentSessionNumber = computed(() => sessionsBeforeCurrent.value.length + 1)

const allGroups = computed<ItemGroup[]>(() => {
  if (!tool.value) return []
  const toolGroups: ItemGroup[] = (tool.value.groups ?? []).map(group => ({
    slug: group.slug,
    label: group.label,
    kind: 'tool',
    items: group.itemSlugs
      .map(slug => tool.value?.items.find(item => item.slug === slug))
      .filter((item): item is IEvalItem => item !== undefined)
      .map(item => ({ ...item, kind: 'tool' })),
  }))

  toolGroups.push({
    slug: 'general-counselling',
    label: 'General Counselling Competencies (SME)',
    kind: 'counselling',
    items: counsellingTool.items.map(item => ({ ...item, kind: 'counselling' })),
  })

  return toolGroups
})

const selectedToolItems = computed(() => selectedItemSlugs.value.length)
const selectedCounsellingCount = computed(() => selectedCounsellingSlugs.value.length)
const activeRound = computed(() => rounds[activeRoundIndex.value])

const dateBeforeReopenedJourney = computed(() =>
  !!latestJourneyDateStr.value && evalDateStr.value < latestJourneyDateStr.value)

const isValid = computed(() =>
  !!mentee.value
  && !!tool.value
  && !!userStore.currentUser
  && phase.value !== null
  && !!evalDateStr.value
  && !dateBeforeReopenedJourney.value
  && selectedItemSlugs.value.length > 0
  && rounds.length > 0)

const evaluationItemCount = computed(() =>
  selectedItemSlugs.value.length + selectedCounsellingSlugs.value.length)

const scoredInActiveRound = computed(() => {
  if (!activeRound.value) return 0
  return evaluationSlugs.value.filter(slug => hasScore(activeRound.value, slug)).length
})

const evaluationSlugs = computed(() => [
  ...selectedItemSlugs.value,
  ...selectedCounsellingSlugs.value,
])

const hasUnsavedChanges = computed(() => {
  if (editId.value) return true
  return phase.value !== null
    || !!sessionNotes.value.trim()
    || rounds.some(round => Object.keys(round.scores).length > 0
      || Object.values(round.notes).some(note => note.trim())
      || !!round.notesText.trim())
    || selectedItemSlugs.value.length > 0
    || selectedCounsellingSlugs.value.length !== counsellingTool.items.length
})

const allowNavigation = ref(false)
const showLeaveConfirm = ref(false)
let leaveResolver: ((value: boolean) => void) | null = null

function createDraftRound(index: number, round?: IRound): DraftRound {
  const scores: Record<string, ScoreValue> = {}
  const notes: Record<string, string> = {}

  if (round) {
    for (const score of [...round.itemScores, ...round.counsellingScores]) {
      scores[score.itemSlug] = score.menteeScore
      if (score.notes) notes[score.itemSlug] = score.notes
    }
  }

  return {
    roundId: round?.roundId ?? `round-${crypto.randomUUID()}`,
    label: round?.label ?? `Round ${index}`,
    scores,
    notes,
    notesText: round?.notes ?? '',
    startedAt: round?.startedAt ?? Date.now(),
  }
}

function hasScore(round: DraftRound, slug: string): boolean {
  return round.scores[slug] !== undefined
}

function getScore(round: DraftRound | undefined, slug: string): ScoreValue {
  return round && hasScore(round, slug) ? round.scores[slug] : null
}

function scoresInSession(session: ISession): IRound[] {
  return session.rounds?.length
    ? session.rounds
    : [{
        roundId: `legacy-${session._id}`,
        label: 'Round 1',
        itemScores: session.itemScores ?? [],
        counsellingScores: session.counsellingScores ?? [],
        startedAt: session.createdAt,
      }]
}

function previousAssessment(itemSlug: string): PreviousAssessment | undefined {
  for (let index = activeRoundIndex.value - 1; index >= 0; index--) {
    const round = rounds[index]
    if (round && hasScore(round, itemSlug)) {
      return {
        score: round.scores[itemSlug],
        evalDate: new Date(`${evalDateStr.value}T12:00:00`).getTime(),
        sessionNumber: currentSessionNumber.value,
        roundNumber: index + 1,
      }
    }
  }

  for (const session of [...sessionsBeforeCurrent.value].reverse()) {
    const sessionRounds = scoresInSession(session)
    for (let roundIndex = sessionRounds.length - 1; roundIndex >= 0; roundIndex--) {
      const round = sessionRounds[roundIndex]
      const score = [...(round?.itemScores ?? []), ...(round?.counsellingScores ?? [])]
        .find(candidate => candidate.itemSlug === itemSlug)
      if (score) {
        return {
          score: score.menteeScore,
          evalDate: session.evalDate,
          sessionNumber: sessionsBeforeCurrent.value.findIndex(previous => previous._id === session._id) + 1,
          roundNumber: roundIndex + 1,
        }
      }
    }
  }

  return undefined
}

function isSelected(item: GroupItem): boolean {
  return item.kind === 'counselling'
    ? selectedCounsellingSlugs.value.includes(item.slug)
    : selectedItemSlugs.value.includes(item.slug)
}

interface PlanChange {
  kind: ItemKind
  slug: string
  selected: boolean
}

const showPlanConfirm = ref(false)
const pendingPlanChanges = ref<PlanChange[]>([])
const showRoundConfirm = ref(false)
const pendingRoundIndex = ref<number | null>(null)

function requestPlanChanges(changes: PlanChange[]) {
  const scoredRemovals = changes.filter(change =>
    !change.selected && rounds.some(round =>
      hasScore(round, change.slug) || !!round.notes[change.slug]?.trim()))

  if (scoredRemovals.length) {
    pendingPlanChanges.value = changes
    showPlanConfirm.value = true
    return
  }
  applyPlanChanges(changes)
}

function applyPlanChanges(changes: PlanChange[]) {
  for (const change of changes) {
    const selected = change.kind === 'counselling'
      ? selectedCounsellingSlugs
      : selectedItemSlugs

    if (change.selected && !selected.value.includes(change.slug)) {
      selected.value = [...selected.value, change.slug]
    } else if (!change.selected && selected.value.includes(change.slug)) {
      selected.value = selected.value.filter(slug => slug !== change.slug)
      for (const round of rounds) {
        delete round.scores[change.slug]
        delete round.notes[change.slug]
      }
    }
  }
}

function confirmPlanChanges() {
  applyPlanChanges(pendingPlanChanges.value)
  pendingPlanChanges.value = []
  showPlanConfirm.value = false
}

function toggleItem(item: GroupItem) {
  requestPlanChanges([{
    kind: item.kind,
    slug: item.slug,
    selected: !isSelected(item),
  }])
}

function setGroupSelection(group: ItemGroup, selected: boolean) {
  requestPlanChanges(group.items
    .filter(item => isSelected(item) !== selected)
    .map(item => ({ kind: item.kind, slug: item.slug, selected })))
}

function confirmPlan() {
  if (!selectedItemSlugs.value.length) {
    toast.add({
      title: 'Select at least one tool item',
      description: 'Choose the competencies to observe before continuing.',
      color: 'warning',
    })
    return
  }
  activeStep.value = 'observe'
}

function addRound() {
  const highestRoundNumber = rounds.reduce((highest, round) => {
    const match = /^Round (\d+)$/.exec(round.label)
    return Math.max(highest, match ? Number(match[1]) : 0)
  }, 0)
  rounds.push(createDraftRound(highestRoundNumber + 1))
  activeRoundIndex.value = rounds.length - 1
}

function removeRound(index: number) {
  if (rounds.length <= 1 || !rounds[index]) return
  const hasData = Object.keys(rounds[index].scores).length > 0
    || !!rounds[index].notesText.trim()
    || Object.values(rounds[index].notes).some(note => note.trim())
  if (!hasData) {
    rounds.splice(index, 1)
    activeRoundIndex.value = Math.min(activeRoundIndex.value, rounds.length - 1)
    return
  }
  pendingRoundIndex.value = index
  showRoundConfirm.value = true
}

function confirmRemoveRound() {
  if (pendingRoundIndex.value !== null && rounds.length > 1) {
    rounds.splice(pendingRoundIndex.value, 1)
    activeRoundIndex.value = Math.min(activeRoundIndex.value, rounds.length - 1)
  }
  pendingRoundIndex.value = null
  showRoundConfirm.value = false
}

function setScore(slug: string, value: ScoreValue) {
  if (activeRound.value) {
    activeRound.value.scores = {
      ...activeRound.value.scores,
      [slug]: value,
    }
  }
}

function setItemNote(slug: string, value: string) {
  if (activeRound.value) activeRound.value.notes[slug] = value
}

function roundScores(round: DraftRound, kind: ItemKind): IItemScore[] {
  const selectedSlugs = kind === 'tool' ? selectedItemSlugs.value : selectedCounsellingSlugs.value
  const itemOrder = kind === 'tool'
    ? (tool.value?.items.map(item => item.slug) ?? [])
    : counsellingTool.items.map(item => item.slug)
  const allowedSlugs = new Set(selectedSlugs)

  return itemOrder
    .filter(slug => allowedSlugs.has(slug) && hasScore(round, slug))
    .map(itemSlug => ({
      itemSlug,
      menteeScore: round.scores[itemSlug] ?? null,
      notes: round.notes[itemSlug]?.trim() || undefined,
    }))
}

function buildReviewPayload() {
  if (!mentee.value || !tool.value) return null
  const reviewRounds: IRound[] = rounds.map(round => ({
    roundId: round.roundId,
    label: round.label.trim() || `Round ${rounds.indexOf(round) + 1}`,
    itemScores: roundScores(round, 'tool'),
    counsellingScores: roundScores(round, 'counselling'),
    notes: round.notesText.trim() || undefined,
    startedAt: round.startedAt,
    completedAt: Date.now(),
  }))

  const metadata = allGroups.value.flatMap(group => group.items)
  const items = metadata
    .filter(item => isSelected(item))
    .map(item => {
      let evaluated = false
      let score: ScoreValue = null
      let note = ''
      for (const round of reviewRounds) {
        const scores = item.kind === 'counselling' ? round.counsellingScores : round.itemScores
        const current = scores.find(candidate => candidate.itemSlug === item.slug)
        if (current) {
          evaluated = true
          if (current.menteeScore !== null) score = current.menteeScore
          if (current.notes) note = current.notes
        }
      }
      return {
        slug: item.slug,
        number: item.number,
        title: item.title,
        category: item.category,
        type: item.kind,
        score,
        notes: note,
        evaluated,
      }
    })

  return {
    menteeId: mentee.value.id,
    menteeName: menteeName.value,
    toolSlug: tool.value.slug,
    toolLabel: tool.value.label,
    items,
    rounds: reviewRounds,
    selectedItemSlugs: [...selectedItemSlugs.value],
    selectedCounsellingSlugs: [...selectedCounsellingSlugs.value],
    sessionId: editId.value,
    sessionRev: sessionStore.sessions.find(session => session._id === editId.value)?._rev,
    phase: phase.value,
    evalDate: evalDateStr.value,
    reopenReason: reopenReason.value,
    notes: sessionNotes.value,
  }
}

function snapshotDraft() {
  sessionStorage.setItem('penplus_session_draft', JSON.stringify({
    menteeId: menteeId.value,
    toolSlug: toolSlug.value,
    sessionId: editId.value,
    phase: phase.value,
    evalDateStr: evalDateStr.value,
    sessionNotes: sessionNotes.value,
    activeStep: activeStep.value,
    activeRoundIndex: activeRoundIndex.value,
    selectedItemSlugs: selectedItemSlugs.value,
    selectedCounsellingSlugs: selectedCounsellingSlugs.value,
    rounds,
  }))
}

function hydrateDraft(draft: Record<string, unknown>): boolean {
  if (draft.menteeId !== menteeId.value || draft.toolSlug !== toolSlug.value || draft.sessionId !== editId.value) {
    return false
  }
  phase.value = (draft.phase as MentorshipPhase | null) ?? null
  evalDateStr.value = String(draft.evalDateStr ?? evalDateStr.value)
  sessionNotes.value = String(draft.sessionNotes ?? '')
  activeStep.value = draft.activeStep === 'observe' ? 'observe' : 'plan'
  selectedItemSlugs.value = Array.isArray(draft.selectedItemSlugs)
    ? draft.selectedItemSlugs.filter((slug): slug is string => typeof slug === 'string')
    : []
  selectedCounsellingSlugs.value = Array.isArray(draft.selectedCounsellingSlugs)
    ? draft.selectedCounsellingSlugs.filter((slug): slug is string => typeof slug === 'string')
    : counsellingTool.items.map(item => item.slug)

  const storedRounds = Array.isArray(draft.rounds) ? draft.rounds as DraftRound[] : []
  if (storedRounds.length) {
    rounds.splice(0, rounds.length, ...storedRounds.map((round, index) => ({
      roundId: round.roundId,
      label: round.label || `Round ${index + 1}`,
      scores: round.scores ?? {},
      notes: round.notes ?? {},
      notesText: round.notesText ?? '',
      startedAt: round.startedAt ?? Date.now(),
    })))
    const index = Number(draft.activeRoundIndex)
    activeRoundIndex.value = Number.isInteger(index) ? Math.max(0, Math.min(index, rounds.length - 1)) : 0
  }
  return true
}

function hydrateSession(session: ISession) {
  phase.value = session.phase
  evalDateStr.value = format(new Date(session.evalDate), 'yyyy-MM-dd')
  sessionNotes.value = session.notes ?? ''
  const storedRounds = session.rounds?.length ? session.rounds : [{
    roundId: `legacy-${session._id}`,
    label: 'Round 1',
    itemScores: session.itemScores ?? [],
    counsellingScores: session.counsellingScores ?? [],
    startedAt: session.createdAt,
  }]

  selectedItemSlugs.value = session.selectedItemSlugs
    ?? [...new Set(storedRounds.flatMap(round => round.itemScores.map(score => score.itemSlug)))]
  selectedCounsellingSlugs.value = session.selectedCounsellingSlugs
    ?? [...new Set(storedRounds.flatMap(round => round.counsellingScores.map(score => score.itemSlug)))]

  rounds.splice(0, rounds.length, ...storedRounds.map((round, index) => createDraftRound(index + 1, round)))
  activeRoundIndex.value = 0
  activeStep.value = 'observe'
}

const editingSession = computed(() =>
  editId.value ? sessionStore.sessions.find(session => session._id === editId.value) : undefined)

onMounted(() => {
  const draft = sessionStorage.getItem('penplus_session_draft')
  if (draft) {
    try {
      if (hydrateDraft(JSON.parse(draft) as Record<string, unknown>)) {
        sessionStorage.removeItem('penplus_session_draft')
        return
      }
    } catch (error) {
      console.error('Unable to restore the session draft', error)
    }
    sessionStorage.removeItem('penplus_session_draft')
  }

  if (editingSession.value) {
    hydrateSession(editingSession.value)
    return
  }
  if (editId.value) {
    toast.add({ title: 'Session not found', description: 'The session could not be loaded for editing.', color: 'error' })
    router.replace(`/mentees/${menteeId.value}`)
  }
})

function confirmLeave(): Promise<boolean> {
  showLeaveConfirm.value = true
  return new Promise(resolve => { leaveResolver = resolve })
}

function resolveLeave(result: boolean) {
  showLeaveConfirm.value = false
  leaveResolver?.(result)
  leaveResolver = null
}

onBeforeRouteLeave(() => {
  if (allowNavigation.value) {
    allowNavigation.value = false
    return true
  }
  if (!hasUnsavedChanges.value) return true
  return confirmLeave()
})

function viewEvaluation() {
  if (!isValid.value) {
    if (!selectedItemSlugs.value.length) activeStep.value = 'plan'
    return
  }
  const payload = buildReviewPayload()
  if (!payload) return
  snapshotDraft()
  sessionStorage.setItem('penplus_preview', JSON.stringify(payload))
  allowNavigation.value = true
  router.push('/sessions/preview')
}
</script>

<template>
  <div class="pb-8">
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ menteeName }}</p>
          <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ tool?.label }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1 text-xs">
          <span class="rounded-full px-2.5 py-1" :class="activeStep === 'plan' ? 'bg-primary-100 font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-200' : 'text-gray-500'">
            1. Plan
          </span>
          <span class="text-gray-300">›</span>
          <span class="rounded-full px-2.5 py-1" :class="activeStep === 'observe' ? 'bg-primary-100 font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-200' : 'text-gray-500'">
            2. Observe
          </span>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-5 px-4 py-5">
      <section
        v-if="tool"
        class="rounded-xl border px-4 py-3"
        :style="{ backgroundColor: tool.theme?.headerBg, borderColor: tool.theme?.accent, color: tool.theme?.headerText }"
      >
        <p class="text-xs font-semibold uppercase tracking-wider opacity-75">{{ editingSession ? 'Edit session' : 'Mentorship session' }}</p>
        <h1 class="mt-1 text-lg font-bold">{{ tool.label }}</h1>
        <p class="text-sm">{{ menteeName }}<span v-if="mentee?.facilityId"> · {{ mentee.facilityId }}</span></p>
      </section>

      <div
        v-if="totalPreviousSessions > 0"
        class="flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2 text-sm text-orange-800 dark:bg-orange-950/40 dark:text-orange-200"
      >
        <UIcon name="i-heroicons-information-circle" class="h-4 w-4 shrink-0" />
        <p>{{ totalPreviousSessions }} previous session{{ totalPreviousSessions === 1 ? '' : 's' }} in this journey.</p>
      </div>

      <div
        v-if="reopenReason"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
      >
        <p class="font-medium">This session reopens a completed journey.</p>
        <p class="mt-0.5 text-xs">Reason: {{ reopenReason }}</p>
      </div>

      <section class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Evaluation date <span class="text-red-500">*</span>
          </label>
          <UInput v-model="evalDateStr" type="date" :min="latestJourneyDateStr" class="w-full" />
          <p v-if="dateBeforeReopenedJourney" class="mt-1 text-xs text-red-500">
            A reopening session can't be dated before {{ latestJourneyDateStr }}.
          </p>
        </div>
        <div>
          <SessionPhaseSelector v-model="phase" />
        </div>
      </section>

      <section v-if="activeStep === 'plan'" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-2">
          <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Plan today's observation</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Select the competencies you expect to observe. You can choose a subset in each section.
            </p>
          </div>
          <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
            {{ selectedToolItems }} tool items · {{ selectedCounsellingCount }}/{{ counsellingTool.items.length }} counselling
          </p>
        </div>

        <section
          v-for="group in allGroups"
          :key="group.slug"
          class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
        >
          <header
            class="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2.5"
            :style="group.kind === 'tool' && tool?.theme
              ? { backgroundColor: tool.theme.headerBg, color: tool.theme.headerText, borderColor: tool.theme.accent }
              : {}"
          >
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wide">{{ group.label }}</h3>
              <p class="mt-0.5 text-[11px] opacity-75">
                {{ group.items.filter(isSelected).length }} of {{ group.items.length }} selected
              </p>
            </div>
            <div class="flex gap-1">
              <UButton size="xs" variant="soft" color="neutral" @click="setGroupSelection(group, true)">Select all</UButton>
              <UButton size="xs" variant="ghost" color="neutral" @click="setGroupSelection(group, false)">Clear</UButton>
            </div>
          </header>
          <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <label
              v-for="item in group.items"
              :key="item.slug"
              class="flex cursor-pointer items-start gap-3 px-3 py-3"
              :class="item.isAdvanced ? 'bg-gray-100 dark:bg-gray-800/70' : ''"
            >
              <UCheckbox
                :model-value="isSelected(item)"
                class="mt-0.5"
                @update:model-value="toggleItem(item)"
              />
              <span class="inline-flex min-w-9 shrink-0 items-center justify-center rounded bg-gray-100 px-2 py-1 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                {{ item.number }}
              </span>
              <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ item.title }}</span>
              <span v-if="item.isAdvanced" class="shrink-0 rounded-full bg-gray-300 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                Advanced
              </span>
            </label>
          </div>
        </section>

        <div class="sticky bottom-2 flex justify-end rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
          <UButton color="primary" :disabled="selectedToolItems === 0" @click="confirmPlan">
            Confirm plan · {{ evaluationItemCount }} items
            <UIcon name="i-heroicons-arrow-right" class="ml-2 h-4 w-4" />
          </UButton>
        </div>
      </section>

      <section v-else class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Observe selected competencies</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ scoredInActiveRound }}/{{ evaluationItemCount }} scored in this round
            </p>
          </div>
          <UButton size="sm" variant="soft" color="neutral" @click="activeStep = 'plan'">
            <UIcon name="i-heroicons-pencil-square" class="mr-1 h-4 w-4" />
            Edit plan
          </UButton>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-for="(round, index) in rounds"
              :key="round.roundId"
              type="button"
              class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium"
              :class="activeRoundIndex === index
                ? 'border-primary-500 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200'
                : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'"
              @click="activeRoundIndex = index"
            >
              {{ round.label || `Round ${index + 1}` }}
            </button>
            <UButton size="xs" variant="soft" color="primary" @click="addRound">
              <UIcon name="i-heroicons-plus" class="mr-1 h-4 w-4" />
              Add round
            </UButton>
            <UButton
              v-if="rounds.length > 1"
              size="xs"
              variant="ghost"
              color="error"
              :aria-label="`Delete ${activeRound?.label}`"
              @click="removeRound(activeRoundIndex)"
            >
              <UIcon name="i-heroicons-trash" class="h-4 w-4" />
            </UButton>
          </div>
          <div v-if="activeRound" class="mt-3 grid gap-3 sm:grid-cols-2">
            <UInput v-model="activeRound.label" :aria-label="`Label for round ${activeRoundIndex + 1}`" />
            <UTextarea v-model="activeRound.notesText" :rows="1" placeholder="Notes for this observation round (optional)" />
          </div>
        </section>

        <ScoreLegend />

        <section
          v-for="group in allGroups"
          :key="group.slug"
          v-show="group.items.some(item => isSelected(item))"
          class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
        >
          <header
            class="border-b px-3 py-2.5"
            :style="group.kind === 'tool' && tool?.theme
              ? { backgroundColor: tool.theme.headerBg, color: tool.theme.headerText, borderColor: tool.theme.accent }
              : {}"
          >
            <h3 class="text-xs font-bold uppercase tracking-wide">{{ group.label }}</h3>
          </header>
          <div class="space-y-2 bg-gray-50 p-2 dark:bg-gray-950">
            <SessionScoreRow
              v-for="item in group.items.filter(isSelected)"
              :key="item.slug"
              :item="item"
              :score="getScore(activeRound, item.slug)"
              :is-scored="!!activeRound && hasScore(activeRound, item.slug)"
              :previous-assessment="previousAssessment(item.slug)"
              :note="activeRound?.notes[item.slug]"
              :counselling="item.kind === 'counselling'"
              @score="setScore(item.slug, $event)"
              @note="setItemNote(item.slug, $event)"
            />
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
          <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Session notes <span class="font-normal normal-case tracking-normal">(optional)</span>
          </label>
          <UTextarea v-model="sessionNotes" :rows="3" placeholder="Overall observations or follow-up actions…" class="w-full" />
        </section>

        <div class="sticky bottom-2 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
          <UButton variant="soft" color="neutral" @click="activeStep = 'plan'">
            <UIcon name="i-heroicons-arrow-left" class="mr-1 h-4 w-4" />
            Back to plan
          </UButton>
          <UButton color="success" :disabled="!isValid" :loading="saving" @click="viewEvaluation">
            Review session
            <UIcon name="i-heroicons-eye" class="ml-2 h-4 w-4" />
          </UButton>
        </div>
      </section>
    </main>

    <UModal v-model:open="showPlanConfirm" title="Remove recorded item scores?">
      <template #body>
        <p class="text-sm text-gray-700 dark:text-gray-300">
          Removing this selection will also remove its scores and notes from every round in this session. Continue?
        </p>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton variant="ghost" color="neutral" @click="showPlanConfirm = false; pendingPlanChanges = []">Keep scores</UButton>
          <UButton color="error" @click="confirmPlanChanges">Remove scores</UButton>
        </div>
      </template>
    </UModal>

    <UModal v-model:open="showRoundConfirm" title="Delete this round?">
      <template #body>
        <p class="text-sm text-gray-700 dark:text-gray-300">
          Scores and notes in this round will be permanently removed from the session.
        </p>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton variant="ghost" color="neutral" @click="showRoundConfirm = false; pendingRoundIndex = null">Keep round</UButton>
          <UButton color="error" @click="confirmRemoveRound">Delete round</UButton>
        </div>
      </template>
    </UModal>

    <UModal v-model:open="showLeaveConfirm" title="Discard this session?">
      <template #body>
        <p class="text-sm text-gray-700 dark:text-gray-300">
          Your plan, round scores, and notes haven't been saved. Leaving now will discard them.
        </p>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton variant="ghost" color="neutral" @click="resolveLeave(false)">Keep editing</UButton>
          <UButton color="error" @click="resolveLeave(true)">Discard</UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>
