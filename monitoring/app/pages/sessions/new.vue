<script setup lang="ts">
import { format } from 'date-fns'
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { counsellingTool, getToolBySlug, toolIncludesCounselling } from '~/data/evaluationItemData'
import { getCompetencyStatus, getLatestItemScores } from '~/composables/useCompetency'
import type { IItemScore } from '~/interfaces/IItemScore'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import type { IRound, ISession, MentorshipPhase } from '~/interfaces/ISession'
import type { IGapEntry } from '~/interfaces/IGapEntry'
import GapForm from '~/components/gap/GapForm.vue'
import GapCard from '~/components/gap/GapCard.vue'
import SessionScoreRow from '~/components/session/ScoreRow.vue'
import ScoreLegend from '~/components/session/ScoreLegend.vue'
import { echoAccuracy } from '~/composables/useSessionCalculations'

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
  // Competencies chosen for this round. Each round can choose different items.
  itemSlugs: string[]
  counsellingSlugs: string[]
  // Gaps found in this round. Drafts until the session is saved.
  gaps: IGapEntry[]
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
const gapStore = useGapStore()
const toast = useToast()

await Promise.all([userStore.loadUsers(), sessionStore.loadAll(), gapStore.loadAll()])

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
// Session notes are no longer entered (client request): gaps take their place.
// Notes on older sessions are kept and saved unchanged when they're edited.
const sessionNotes = ref('')

// Gaps are logged per round and held on the round as drafts until the session
// is saved, so a discarded session (or deleted round) leaves no gaps behind.
const showGapForm = ref(false)

function addDraftGap(gap: IGapEntry) {
  const round = rounds[activeRoundIndex.value]
  if (round) round.gaps = [...round.gaps, gap]
}

function removeDraftGap(index: number) {
  const round = rounds[activeRoundIndex.value]
  if (round) round.gaps = round.gaps.filter((_, i) => i !== index)
}
const echocardiogramsPerformedInput = ref('')
const echocardiogramsCorrectDiagnosisInput = ref('')
const saving = ref(false)
const activeStep = ref<'plan' | 'observe'>('plan')
const activeRoundIndex = ref(0)
// Counselling items chosen by default in a new round: all of them, unless the
// tool leaves counselling out (Echocardiogram).
function defaultCounsellingSlugs(): string[] {
  return toolIncludesCounselling(tool.value) ? counsellingTool.items.map(item => item.slug) : []
}

const rounds = reactive<DraftRound[]>([createDraftRound(1)])

// The competency screen edits the active round's selection.
const selectedItemSlugs = computed<string[]>({
  get: () => rounds[activeRoundIndex.value]?.itemSlugs ?? [],
  set: (slugs) => {
    const round = rounds[activeRoundIndex.value]
    if (round) round.itemSlugs = slugs
  },
})
const selectedCounsellingSlugs = computed<string[]>({
  get: () => rounds[activeRoundIndex.value]?.counsellingSlugs ?? [],
  set: (slugs) => {
    const round = rounds[activeRoundIndex.value]
    if (round) round.counsellingSlugs = slugs
  },
})

// Everything evaluated in any round of this session, in tool order.
const sessionItemSlugs = computed(() => {
  const chosen = new Set(rounds.flatMap(round => round.itemSlugs))
  return (tool.value?.items ?? []).map(item => item.slug).filter(slug => chosen.has(slug))
})
const sessionCounsellingSlugs = computed(() => {
  const chosen = new Set(rounds.flatMap(round => round.counsellingSlugs))
  return counsellingTool.items.map(item => item.slug).filter(slug => chosen.has(slug))
})

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

// Gaps already saved against the active round of the session being edited.
// Gaps saved before rounds were recorded on gaps show under the first round.
const existingRoundGaps = computed(() => {
  if (!editId.value) return []
  const round = rounds[activeRoundIndex.value]
  return gapStore.gaps.filter(gap => gap.sessionId === editId.value
    && (gap.roundId ? gap.roundId === round?.roundId : activeRoundIndex.value === 0))
})

// Open gaps from earlier sessions in this journey, oldest first, so the mentor
// has them in front of them when starting the next visit.
const openEarlierGaps = computed(() =>
  gapStore.gaps
    .filter(gap => gap.evaluationGroupId === evaluationGroupId.value
      && !gap.resolvedAt
      && (!editId.value || gap.sessionId !== editId.value))
    .sort((a, b) => a.identifiedAt - b.identifiedAt))

// Items whose latest score in earlier sessions is already 4 or 5. Under the
// competency rule they stay achieved, so mentors don't need to re-rate them.
const achievedItems = computed(() => {
  const achieved = new Map<string, { score: number; evalDate: number }>()
  for (const [slug, entry] of getLatestItemScores(sessionsBeforeCurrent.value)) {
    if (entry.score >= 4) achieved.set(slug, entry)
  }
  return achieved
})

function achievedLabel(slug: string): string | undefined {
  const entry = achievedItems.value.get(slug)
  return entry ? `Achieved · ${entry.score} on ${format(new Date(entry.evalDate), 'd MMM')}` : undefined
}

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

  if (!toolIncludesCounselling(tool.value)) return toolGroups

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
const advancedItemCount = computed(() => tool.value?.items.filter(item => item.isAdvanced).length ?? 0)
const selectedAdvancedCount = computed(() =>
  tool.value?.items.filter(item =>
    item.isAdvanced && selectedItemSlugs.value.includes(item.slug)).length ?? 0)
const activeRound = computed(() => rounds[activeRoundIndex.value])
const echocardiogramsPerformed = computed(() => parseCount(echocardiogramsPerformedInput.value))
const echocardiogramsCorrectDiagnosis = computed(() => parseCount(echocardiogramsCorrectDiagnosisInput.value))
const echoActivityError = computed(() => {
  if (tool.value?.slug !== 'echo') return undefined
  if (echocardiogramsPerformed.value === undefined || echocardiogramsCorrectDiagnosis.value === undefined) {
    return 'Enter both echocardiogram counts. Use 0 if none were performed.'
  }
  if (echocardiogramsCorrectDiagnosis.value > echocardiogramsPerformed.value) {
    return 'The number with a correct final diagnosis cannot exceed the number performed.'
  }
  return undefined
})

const dateBeforeReopenedJourney = computed(() =>
  !!latestJourneyDateStr.value && evalDateStr.value < latestJourneyDateStr.value)

// Show the echo count errors once the mentor has tried to move on.
const showEchoErrors = ref(false)

const echoVisitAccuracy = computed(() =>
  echoAccuracy(echocardiogramsPerformed.value, echocardiogramsCorrectDiagnosis.value))

// E8 "Correctly identifies diagnosis according to the PEN-Plus protocol" is
// the item the counts give direct evidence for. Information only: it never
// sets or suggests a score.
const ECHO_DIAGNOSIS_ITEM = 'echo-E8'

function itemContext(slug: string): string | undefined {
  if (slug !== ECHO_DIAGNOSIS_ITEM || tool.value?.slug !== 'echo') return undefined
  const accuracy = echoVisitAccuracy.value
  if (!accuracy || echoActivityError.value) {
    return 'Diagnostic accuracy for this visit will show here once the echocardiogram counts are entered.'
  }
  return `This visit: ${accuracy.text} of supervised echocardiograms.`
}

type ReviewBlocker = { message: string; target: string }

// Why Review is disabled, with the place to fix it. Ordered top to bottom.
const reviewBlocker = computed<ReviewBlocker | undefined>(() => {
  if (!evalDateStr.value) return { message: 'Choose an evaluation date', target: 'session-date' }
  if (dateBeforeReopenedJourney.value) return { message: 'Fix the evaluation date', target: 'session-date' }
  if (phase.value === null) return { message: 'Select a mentorship phase', target: 'session-phase' }
  if (echoActivityError.value) return { message: 'Enter the echocardiogram counts', target: 'echo-activity' }
  return undefined
})

function goToBlocker() {
  const blocker = reviewBlocker.value
  if (!blocker) return
  if (blocker.target === 'echo-activity') showEchoErrors.value = true
  document.getElementById(blocker.target)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const isValid = computed(() =>
  !!mentee.value
  && !!tool.value
  && !!userStore.currentUser
  && phase.value !== null
  && !!evalDateStr.value
  && !dateBeforeReopenedJourney.value
  && !echoActivityError.value
  && sessionItemSlugs.value.length > 0
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
    || rounds.some(round => round.gaps.length > 0)
    || !!echocardiogramsPerformedInput.value
    || !!echocardiogramsCorrectDiagnosisInput.value
    || rounds.some(round => Object.keys(round.scores).length > 0
      || Object.values(round.notes).some(note => note.trim())
      || !!round.notesText.trim())
    || rounds.some(round => round.itemSlugs.length > 0
      || round.counsellingSlugs.length !== defaultCounsellingSlugs().length)
})

const allowNavigation = ref(false)
const showLeaveConfirm = ref(false)
let leaveResolver: ((value: boolean) => void) | null = null

function createDraftRound(
  index: number,
  round?: IRound,
  selection?: { items: string[]; counselling: string[] },
): DraftRound {
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
    itemSlugs: [...(selection?.items ?? round?.selectedItemSlugs ?? [])],
    counsellingSlugs: [...(selection?.counselling ?? round?.selectedCounsellingSlugs
      ?? defaultCounsellingSlugs())],
    gaps: [],
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
  const round = rounds[activeRoundIndex.value]
  const scoredRemovals = changes.filter(change =>
    !change.selected && !!round
    && (hasScore(round, change.slug) || !!round.notes[change.slug]?.trim()))

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
      const round = rounds[activeRoundIndex.value]
      if (round) {
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
  const previous = rounds[activeRoundIndex.value]
  rounds.push(createDraftRound(highestRoundNumber + 1, undefined, {
    items: previous?.itemSlugs ?? [],
    counselling: previous?.counsellingSlugs ?? defaultCounsellingSlugs(),
  }))
  activeRoundIndex.value = rounds.length - 1
  // Let the mentor confirm or change which competencies this round covers.
  activeStep.value = 'plan'
}

function removeRound(index: number) {
  if (rounds.length <= 1 || !rounds[index]) return
  const hasData = Object.keys(rounds[index].scores).length > 0
    || rounds[index].gaps.length > 0
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
    // N/A has no notes field, so don't keep a hidden note from an earlier score.
    if (value === null) delete activeRound.value.notes[slug]
  }
}

function setItemNote(slug: string, value: string) {
  if (activeRound.value) activeRound.value.notes[slug] = value
}

function parseCount(value: string): number | undefined {
  const normalized = value.trim()
  if (!/^\d+$/.test(normalized)) return undefined
  const count = Number(normalized)
  return Number.isSafeInteger(count) ? count : undefined
}

function roundScores(round: DraftRound, kind: ItemKind): IItemScore[] {
  const selectedSlugs = kind === 'tool' ? round.itemSlugs : round.counsellingSlugs
  const itemOrder = kind === 'tool'
    ? (tool.value?.items.map(item => item.slug) ?? [])
    : defaultCounsellingSlugs()
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
    selectedItemSlugs: [...round.itemSlugs],
    selectedCounsellingSlugs: [...round.counsellingSlugs],
    notes: round.notesText.trim() || undefined,
    startedAt: round.startedAt,
    completedAt: Date.now(),
  }))

  const metadata = allGroups.value.flatMap(group => group.items)
  const items = metadata
    .filter(item => item.kind === 'counselling'
      ? sessionCounsellingSlugs.value.includes(item.slug)
      : sessionItemSlugs.value.includes(item.slug))
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
    selectedItemSlugs: [...sessionItemSlugs.value],
    selectedCounsellingSlugs: [...sessionCounsellingSlugs.value],
    ...(tool.value.slug === 'echo'
      ? {
          echocardiogramsPerformed: echocardiogramsPerformed.value,
          echocardiogramsCorrectDiagnosis: echocardiogramsCorrectDiagnosis.value,
        }
      : {}),
    sessionId: editId.value,
    sessionRev: sessionStore.sessions.find(session => session._id === editId.value)?._rev,
    phase: phase.value,
    evalDate: evalDateStr.value,
    reopenReason: reopenReason.value,
    notes: sessionNotes.value,
    // Each gap records the round it was found in.
    gaps: rounds.flatMap((round, index) => round.gaps.map(gap => ({
      ...gap,
      roundId: round.roundId,
      roundIndex: index + 1,
    }))),
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
    echocardiogramsPerformedInput: echocardiogramsPerformedInput.value,
    echocardiogramsCorrectDiagnosisInput: echocardiogramsCorrectDiagnosisInput.value,
    activeStep: activeStep.value,
    activeRoundIndex: activeRoundIndex.value,
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
  echocardiogramsPerformedInput.value = String(draft.echocardiogramsPerformedInput ?? '')
  echocardiogramsCorrectDiagnosisInput.value = String(draft.echocardiogramsCorrectDiagnosisInput ?? '')
  activeStep.value = draft.activeStep === 'observe' ? 'observe' : 'plan'
  // Drafts saved before per-round selection kept one session-wide list.
  const legacyItems = Array.isArray(draft.selectedItemSlugs)
    ? draft.selectedItemSlugs.filter((slug): slug is string => typeof slug === 'string')
    : []
  const legacyCounselling = Array.isArray(draft.selectedCounsellingSlugs)
    ? draft.selectedCounsellingSlugs.filter((slug): slug is string => typeof slug === 'string')
    : defaultCounsellingSlugs()

  const storedRounds = Array.isArray(draft.rounds) ? draft.rounds as DraftRound[] : []
  if (storedRounds.length) {
    rounds.splice(0, rounds.length, ...storedRounds.map((round, index) => ({
      roundId: round.roundId,
      label: round.label || `Round ${index + 1}`,
      itemSlugs: Array.isArray(round.itemSlugs) ? round.itemSlugs : legacyItems,
      counsellingSlugs: Array.isArray(round.counsellingSlugs) ? round.counsellingSlugs : legacyCounselling,
      gaps: Array.isArray(round.gaps) ? round.gaps : [],
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
  echocardiogramsPerformedInput.value = session.echocardiogramsPerformed === undefined
    ? ''
    : String(session.echocardiogramsPerformed)
  echocardiogramsCorrectDiagnosisInput.value = session.echocardiogramsCorrectDiagnosis === undefined
    ? ''
    : String(session.echocardiogramsCorrectDiagnosis)
  const storedRounds = session.rounds?.length ? session.rounds : [{
    roundId: `legacy-${session._id}`,
    label: 'Round 1',
    itemScores: session.itemScores ?? [],
    counsellingScores: session.counsellingScores ?? [],
    startedAt: session.createdAt,
  }]

  // Older sessions stored one list for the whole session. A single-round
  // session uses it; otherwise each round falls back to what it scored.
  const singleRound = storedRounds.length === 1
  rounds.splice(0, rounds.length, ...storedRounds.map((round, index) => createDraftRound(index + 1, round, {
    items: round.selectedItemSlugs
      ?? (singleRound && session.selectedItemSlugs ? session.selectedItemSlugs : round.itemScores.map(score => score.itemSlug)),
    counselling: round.selectedCounsellingSlugs
      ?? (singleRound && session.selectedCounsellingSlugs
        ? session.selectedCounsellingSlugs
        : round.counsellingScores.map(score => score.itemSlug)),
  })))
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
    if (!sessionItemSlugs.value.length) activeStep.value = 'plan'
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
    <header class="sticky top-14 z-30 border-b border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="mx-auto flex max-w-4xl items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ menteeName }}</p>
          <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ tool?.label }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1 text-xs">
          <span class="rounded-full px-2.5 py-1" :class="activeStep === 'plan' ? 'bg-primary-100 font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-200' : 'text-gray-500'">
            1. Competencies
          </span>
          <span class="text-gray-300">›</span>
          <span class="rounded-full px-2.5 py-1" :class="activeStep === 'observe' ? 'bg-primary-100 font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-200' : 'text-gray-500'">
            2. Scores
          </span>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-5 px-4 py-5">
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

      <details
        v-if="openEarlierGaps.length"
        class="group rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30"
        :open="openEarlierGaps.length <= 3"
      >
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
          <span class="flex items-center gap-2 font-semibold text-amber-900 dark:text-amber-100">
            <UIcon name="i-heroicons-exclamation-triangle" class="size-5" />
            Open gaps from earlier sessions ({{ openEarlierGaps.length }})
          </span>
          <UIcon name="i-heroicons-chevron-right" class="size-5 shrink-0 text-amber-700 transition-transform group-open:rotate-90" />
        </summary>
        <div class="space-y-2 border-t border-amber-200 p-3 dark:border-amber-900">
          <GapCard v-for="gap in openEarlierGaps" :key="gap._id" :gap="gap" readonly />
          <p class="text-sm text-amber-900 dark:text-amber-200">Resolve gaps from the journey page once they've been addressed.</p>
        </div>
      </details>

      <section class="grid gap-4 sm:grid-cols-2">
        <div id="session-date" class="scroll-mt-36">
          <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Evaluation date <span class="text-red-500">*</span>
          </label>
          <UInput v-model="evalDateStr" type="date" :min="latestJourneyDateStr" class="w-full" />
          <p v-if="dateBeforeReopenedJourney" class="mt-1 text-xs text-red-500">
            A reopening session can't be dated before {{ latestJourneyDateStr }}.
          </p>
        </div>
        <div id="session-phase" class="scroll-mt-36">
          <SessionPhaseSelector v-model="phase" />
        </div>
      </section>

      <div
        v-if="advancedItemCount > 0"
        role="status"
        aria-live="polite"
        class="flex items-start gap-3 rounded-lg border border-gray-400 bg-gray-200 px-3 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
      >
        <span class="shrink-0 rounded bg-gray-700 px-2 py-0.5 text-xs font-semibold uppercase text-white">
          Advanced
        </span>
        <div>
          <p class="font-medium">
            Advanced competencies are not required to be achieved to be considered a competent provider.
          </p>
          <p class="mt-0.5 text-xs text-gray-700 dark:text-gray-300">
            {{ selectedAdvancedCount }} of {{ advancedItemCount }} advanced competencies selected.
          </p>
        </div>
      </div>

      <section v-if="activeStep === 'plan'" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-2">
          <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
              Choose the competencies evaluated<template v-if="rounds.length > 1"> · {{ activeRound?.label }}</template>
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Select the competencies you evaluated, based on all the patients seen with the mentee. Items the mentee has already achieved are marked.
            </p>
          </div>
          <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
            {{ selectedToolItems }} tool items<template v-if="toolIncludesCounselling(tool)"> · {{ selectedCounsellingCount }}/{{ counsellingTool.items.length }} counselling</template>
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
              <h3 class="text-sm font-bold uppercase tracking-wide">{{ group.label }}</h3>
              <p class="mt-0.5 text-xs opacity-75">
                {{ group.items.filter(isSelected).length }} of {{ group.items.length }} selected
              </p>
            </div>
            <div class="flex gap-1">
              <UButton size="md" variant="soft" color="neutral" @click="setGroupSelection(group, true)">Select all</UButton>
              <UButton size="md" variant="ghost" color="neutral" @click="setGroupSelection(group, false)">Clear</UButton>
            </div>
          </header>
          <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <label
              v-for="item in group.items"
              :key="item.slug"
              class="flex min-h-14 cursor-pointer items-start gap-3 border-l-4 border-transparent px-3 py-3.5"
              :class="item.isAdvanced ? 'border-gray-600 bg-gray-200 dark:border-gray-500 dark:bg-gray-800' : ''"
            >
              <UCheckbox
                :model-value="isSelected(item)"
                class="mt-0.5"
                @update:model-value="toggleItem(item)"
              />
              <span
                class="inline-flex min-w-9 shrink-0 items-center justify-center rounded px-2 py-1 text-xs font-bold"
                :class="item.isAdvanced
                  ? 'bg-gray-700 text-white dark:bg-gray-600'
                  : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'"
              >
                {{ item.number }}
              </span>
              <span class="flex-1 text-base leading-snug text-gray-900 dark:text-gray-100">
                {{ item.title }}
                <span
                  v-if="item.kind === 'tool' && achievedLabel(item.slug)"
                  class="mt-1 flex w-fit items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-200"
                >
                  <UIcon name="i-heroicons-check-circle" class="size-4" />
                  {{ achievedLabel(item.slug) }}
                </span>
              </span>
              <span v-if="item.isAdvanced" class="shrink-0 rounded-full bg-gray-700 px-2 py-0.5 text-xs font-semibold uppercase text-white dark:bg-gray-600">
                Advanced
              </span>
            </label>
          </div>
        </section>

        <div class="sticky bottom-2 flex justify-end rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
          <UButton color="primary" size="xl" :disabled="selectedToolItems === 0" @click="confirmPlan">
            Continue to scoring · {{ evaluationItemCount }} items
            <UIcon name="i-heroicons-arrow-right" class="ml-2 h-4 w-4" />
          </UButton>
        </div>
      </section>

      <section v-else class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Score the selected competencies</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ scoredInActiveRound }}/{{ evaluationItemCount }} scored in this round
            </p>
          </div>
          <UButton size="lg" variant="soft" color="neutral" @click="activeStep = 'plan'">
            <UIcon name="i-heroicons-pencil-square" class="mr-1 h-4 w-4" />
            Change competencies
          </UButton>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-for="(round, index) in rounds"
              :key="round.roundId"
              type="button"
              class="flex min-h-11 items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
              :class="activeRoundIndex === index
                ? 'border-primary-500 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200'
                : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'"
              @click="activeRoundIndex = index"
            >
              {{ round.label || `Round ${index + 1}` }}
              <span
                v-if="round.gaps.length"
                class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200"
                :aria-label="`${round.gaps.length} gaps`"
              >
                {{ round.gaps.length }} gap{{ round.gaps.length === 1 ? '' : 's' }}
              </span>
            </button>
            <UButton size="md" variant="soft" color="primary" @click="addRound">
              <UIcon name="i-heroicons-plus" class="mr-1 h-4 w-4" />
              Add round
            </UButton>
            <UButton
              v-if="rounds.length > 1"
              size="md"
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
            <h3 class="text-sm font-bold uppercase tracking-wide">{{ group.label }}</h3>
          </header>
          <div class="space-y-2 bg-gray-50 p-2 dark:bg-gray-950">
            <SessionScoreRow
              v-for="item in group.items.filter(isSelected)"
              :key="item.slug"
              :item="item"
              :score="getScore(activeRound, item.slug)"
              :is-scored="!!activeRound && hasScore(activeRound, item.slug)"
              :previous-assessment="previousAssessment(item.slug)"
              :counselling="item.kind === 'counselling'"
              :context="itemContext(item.slug)"
              :note="activeRound?.notes[item.slug]"
              @note="setItemNote(item.slug, $event)"
              @score="setScore(item.slug, $event)"
            />
          </div>
        </section>

        <SessionEchoActivityCounts
          v-if="tool?.slug === 'echo'"
          v-model:performed="echocardiogramsPerformedInput"
          v-model:correct="echocardiogramsCorrectDiagnosisInput"
          :show-errors="showEchoErrors"
        />

        <section id="session-gaps" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                Gaps identified in {{ activeRound?.label || `Round ${activeRoundIndex + 1}` }}
              </h3>
              <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                Record what the mentee needs to work on, from this round. Gaps are saved with the session and followed up in the journey.
              </p>
            </div>
            <UButton color="warning" variant="soft" icon="i-heroicons-plus" @click="showGapForm = true">
              Log a gap
            </UButton>
          </div>

          <div v-if="existingRoundGaps.length || activeRound?.gaps.length" class="space-y-2">
            <GapCard v-for="gap in existingRoundGaps" :key="gap._id" :gap="gap" readonly />
            <div v-for="(gap, index) in activeRound?.gaps ?? []" :key="`draft-${index}`" class="space-y-1">
              <GapCard :gap="gap" readonly />
              <div class="flex justify-end">
                <UButton variant="link" color="error" icon="i-heroicons-trash" class="px-0" @click="removeDraftGap(index)">
                  Remove
                </UButton>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">No gaps logged for this round yet.</p>

          <div v-if="sessionNotes.trim()" class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Notes recorded with this session</p>
            <p class="mt-0.5 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ sessionNotes }}</p>
          </div>
        </section>

        <div class="sticky bottom-2 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <UButton variant="soft" color="neutral" size="xl" @click="activeStep = 'plan'">
              <UIcon name="i-heroicons-arrow-left" class="mr-1 h-4 w-4" />
              Back to competencies
            </UButton>
            <UButton color="success" size="xl" :disabled="!isValid" :loading="saving" @click="viewEvaluation">
              Review session
              <UIcon name="i-heroicons-eye" class="ml-2 h-4 w-4" />
            </UButton>
          </div>
          <div v-if="reviewBlocker" class="mt-2 flex justify-end">
            <UButton
              variant="link"
              color="warning"
              icon="i-heroicons-arrow-down"
              trailing
              class="px-0 text-right"
              @click="goToBlocker"
            >
              {{ reviewBlocker.message }}
            </UButton>
          </div>
        </div>
      </section>
    </main>

    <GapForm
      v-model:open="showGapForm"
      draft
      :evaluation-group-id="evaluationGroupId"
      :mentee-id="menteeId"
      :evaluator-id="userStore.currentUser?.id ?? ''"
      :tool-slug="toolSlug"
      @drafted="addDraftGap"
    />

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
