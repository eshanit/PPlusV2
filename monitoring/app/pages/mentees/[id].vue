<template>
  <div class="space-y-5">
    <!-- Back + mentee header -->
    <div class="flex items-center gap-3">
      <UButton
        to="/mentees"
        variant="ghost"
        color="neutral"
        icon="i-heroicons-arrow-left"
        size="sm"
        class="-ml-2"
      />
      <div class="flex items-center gap-3 min-w-0">
        <div class="size-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm shrink-0">
          {{ menteeInitials }}
        </div>
        <div class="min-w-0">
          <h1 class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ menteeName }}</h1>
          <p class="text-xs text-gray-500 dark:text-gray-400">{{ journeys.length }} tool{{ journeys.length !== 1 ? 's' : '' }}</p>
        </div>
      </div>
    </div>

    <!-- Start new session -->
    <UButton
      color="primary"
      block
      icon="i-heroicons-plus"
      size="lg"
      @click="showToolPicker = true"
    >
      Start New Session
    </UButton>

    <!-- Existing journeys -->
    <div v-if="journeys.length > 0">
      <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
        Evaluation Journeys
      </h2>

      <ul class="space-y-2">
        <li v-for="journey in journeys" :key="journey.groupId">
          <NuxtLink
            :to="`/sessions/journey?menteeId=${route.params.id}&toolSlug=${journey.toolSlug}`"
            class="block bg-white dark:bg-gray-900 rounded-xl px-4 py-3.5 shadow-sm border border-gray-100 dark:border-gray-800 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-medium text-gray-900 dark:text-white">{{ journey.toolLabel }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  {{ journey.sessionCount }} session{{ journey.sessionCount !== 1 ? 's' : '' }}
                  · Last: {{ formatDate(journey.lastSessionDate) }} (Round {{ journey.lastSessionRound }})
                </p>
              </div>
              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <UBadge
                  v-if="journey.competencyStatus === 'fully_competent'"
                  label="Fully Competent"
                  color="success"
                  variant="solid"
                  size="xs"
                />
                <UBadge
                  v-else-if="journey.competencyStatus === 'basic_competent'"
                  label="Basic Competent"
                  color="success"
                  variant="soft"
                  size="xs"
                />
                <UBadge
                  v-else
                  :label="formatPhase(journey.latestPhase)"
                  :color="phaseColor(journey.latestPhase)"
                  variant="soft"
                  size="xs"
                />
                <UBadge
                  v-if="journey.openGaps > 0"
                  :label="`${journey.openGaps} open gap${journey.openGaps > 1 ? 's' : ''}`"
                  color="warning"
                  variant="soft"
                  size="xs"
                />
              </div>
            </div>
          </NuxtLink>
        </li>
      </ul>
    </div>

    <!-- No journeys yet -->
    <div v-else class="text-center py-10 text-gray-400">
      <UIcon name="i-heroicons-clipboard-document-list" class="size-10 mx-auto mb-2 text-gray-300" />
      <p class="text-sm">No sessions recorded for this mentee yet.</p>
    </div>

    <!-- Tool picker modal -->
    <UModal v-model:open="showToolPicker" title="Select a Tool">
      <template #body>
        <ul class="divide-y divide-gray-100 dark:divide-gray-800 -mx-4 -my-3">
          <li v-for="tool in evaluationTools" :key="tool.slug">
            <button
              class="w-full flex items-center justify-between px-4 py-3.5 transition-colors text-left"
              :class="journeys.find(j => j.toolSlug === tool.slug)?.isClosed
                ? 'opacity-70 hover:bg-gray-50 dark:hover:bg-gray-800/50'
                : 'hover:bg-primary-50 dark:hover:bg-primary-900/20'"
              @click="startSession(tool.slug)"
            >
              <div>
                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ tool.label }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  <template v-if="journeys.find(j => j.toolSlug === tool.slug)?.isClosed">
                    Competency reached — journey closed · tap to reopen
                  </template>
                  <template v-else>
                    {{ tool.items.length }} items
                  </template>
                </p>
              </div>
              <UIcon
                v-if="journeys.find(j => j.toolSlug === tool.slug)?.isClosed"
                name="i-heroicons-lock-closed"
                class="size-4 text-success-500 shrink-0"
              />
              <UIcon
                v-else-if="hasJourney(tool.slug)"
                name="i-heroicons-arrow-path"
                class="size-4 text-primary-500 shrink-0"
              />
              <UIcon v-else name="i-heroicons-plus-circle" class="size-4 text-gray-400 shrink-0" />
            </button>
          </li>
        </ul>
      </template>
    </UModal>

    <!-- Reopen closed journey -->
    <UModal
      v-model:open="showReopen"
      title="Reopen journey"
      :description="reopenTarget ? `${reopenTarget.toolLabel} — ${menteeName}` : ''"
    >
      <template #body>
        <div class="space-y-3">
          <p class="text-sm text-gray-600 dark:text-gray-300">
            This journey closed because every basic competency reached 4 or 5. Reopen it only if
            one or more of those scores was given in error and the mentee needs more sessions.
          </p>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            In the next session, <strong>re-score the items that were scored incorrectly</strong>.
            If every basic item still scores 4 or 5, the journey will close again.
          </p>
          <div>
            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1.5">
              Reason <span class="text-red-500">*</span>
            </label>
            <UTextarea
              v-model="reopenReason"
              :rows="3"
              autoresize
              class="w-full"
              placeholder="e.g. D12 and D14 were scored 4 by mistake — mentee still needs support with metformin titration"
            />
            <p
              v-if="reopenReason.trim().length > 0 && reopenReason.trim().length < MIN_REOPEN_REASON"
              class="text-xs text-red-500 mt-1"
            >
              Please give a little more detail.
            </p>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton variant="ghost" color="neutral" @click="showReopen = false">Cancel</UButton>
          <UButton
            color="warning"
            icon="i-heroicons-lock-open"
            :disabled="reopenReason.trim().length < MIN_REOPEN_REASON"
            @click="confirmReopen"
          >
            Reopen &amp; start session
          </UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { evaluationTools } from '~/data/evaluationItemData'
import type { MentorshipPhase } from '~/interfaces/ISession'
import { getCompetencyStatus, type CompetencyStatus } from '~/composables/useCompetency'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const userStore = useUserStore()
const sessionStore = useSessionStore()
const gapStore = useGapStore()

const menteeId = computed(() => route.params.id as string)
const showToolPicker = ref(false)

const mentee = computed(() => userStore.allUsers.find(u => u.id === menteeId.value))
const menteeName = computed(() => mentee.value ? `${mentee.value.firstname} ${mentee.value.lastname}` : '…')
const menteeInitials = computed(() =>
  mentee.value
    ? `${mentee.value.firstname[0] ?? ''}${mentee.value.lastname[0] ?? ''}`.toUpperCase()
    : '?'
)

interface Journey {
  groupId: string
  toolSlug: string
  toolLabel: string
  sessionCount: number
  lastSessionDate: number
  lastSessionRound: number
  latestPhase: MentorshipPhase | null
  openGaps: number
  competencyStatus: CompetencyStatus
  isClosed: boolean
}

const journeys = computed((): Journey[] => {
  const menteeSessions = sessionStore.sessions.filter(s => s.mentee.id === menteeId.value)

  // Group by evaluationGroupId
  const groups = new Map<string, typeof menteeSessions>()
  for (const s of menteeSessions) {
    const arr = groups.get(s.evaluationGroupId) ?? []
    arr.push(s)
    groups.set(s.evaluationGroupId, arr)
  }

  return Array.from(groups.entries()).map(([groupId, sessions]) => {
    // Same-day sessions share an evalDate, so break ties by round (highest
    // round = most recent that day), then createdAt for any remaining ties.
    const sorted = [...sessions].sort((a, b) =>
      b.evalDate - a.evalDate ||
      (b.roundOfDay ?? 0) - (a.roundOfDay ?? 0) ||
      b.createdAt - a.createdAt
    )
    const toolSlug = sessions[0]!.toolSlug
    const tool = evaluationTools.find(t => t.slug === toolSlug)
    const openGaps = gapStore.gaps.filter(
      g => g.evaluationGroupId === groupId && !g.resolvedAt
    ).length
    const competencyStatus = getCompetencyStatus(sessions, tool)
    const isClosed = competencyStatus === 'basic_competent' || competencyStatus === 'fully_competent'

    return {
      groupId,
      toolSlug,
      toolLabel: tool?.label ?? toolSlug,
      sessionCount: sessions.length,
      lastSessionDate: sorted[0]!.evalDate,
      lastSessionRound: sessionStore.roundNumber(sorted[0]!),
      latestPhase: sorted[0]!.phase,
      openGaps,
      competencyStatus,
      isClosed,
    }
  }).sort((a, b) => b.lastSessionDate - a.lastSessionDate)
})

function hasJourney(toolSlug: string): boolean {
  return journeys.value.some(j => j.toolSlug === toolSlug)
}

function formatDate(ts: number): string {
  return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

function formatPhase(phase: MentorshipPhase | null): string {
  const labels: Record<MentorshipPhase, string> = {
    initial_intensive: 'Intensive',
    ongoing: 'Ongoing',
    supervision: 'Supervision',
  }
  return phase ? labels[phase] : '—'
}

function phaseColor(phase: MentorshipPhase | null): 'info' | 'success' | 'warning' | 'neutral' {
  if (phase === 'initial_intensive') return 'info'
  if (phase === 'ongoing') return 'success'
  if (phase === 'supervision') return 'warning'
  return 'neutral'
}

function startSession(toolSlug: string) {
  const journey = journeys.value.find(j => j.toolSlug === toolSlug)
  showToolPicker.value = false
  if (journey?.isClosed) {
    reopenTarget.value = journey
    reopenReason.value = ''
    showReopen.value = true
    return
  }
  router.push(`/sessions/new?menteeId=${menteeId.value}&toolSlug=${toolSlug}`)
}

// A closed journey can only be reopened with a reason — it's recorded on the
// next session so reports can show why competency was withdrawn.
const showReopen = ref(false)
const reopenTarget = ref<Journey | null>(null)
const reopenReason = ref('')
const MIN_REOPEN_REASON = 10

function confirmReopen() {
  const reason = reopenReason.value.trim()
  if (!reopenTarget.value || reason.length < MIN_REOPEN_REASON) return
  showReopen.value = false
  router.push({
    path: '/sessions/new',
    query: { menteeId: menteeId.value, toolSlug: reopenTarget.value.toolSlug, reopenReason: reason },
  })
}
</script>
