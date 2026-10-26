<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { useSyncStore } from '~/stores/syncStore'
import { getToolBySlug } from '~/data/evaluationItemData'
import { getCompetencyStatus } from '~/composables/useCompetency'
import { getJourneyCoverage } from '~/composables/useCoverage'
import { gapStatus, isGapOverdue } from '~/composables/useGapStatus'
import type { ISession, MentorshipPhase } from '~/interfaces/ISession'

const userStore = useUserStore()
const sessionStore = useSessionStore()
const gapStore = useGapStore()
const syncStore = useSyncStore()

const CONTINUE_LIMIT = 5
const RECENT_LIMIT = 8
const COVERAGE_LIMIT = 5

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning,'
  if (h < 17) return 'Good afternoon,'
  return 'Good evening,'
})

const myId = computed(() => userStore.currentUser?.id)

function toolLabel(slug: string): string {
  return getToolBySlug(slug)?.label ?? slug
}

function menteeName(session: ISession): string {
  return `${session.mentee.firstname} ${session.mentee.lastname}`
}

function formatDate(ts: number): string {
  return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
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

function sessionLink(session: ISession) {
  return {
    path: '/sessions/session',
    query: { sessionId: session._id, menteeId: session.mentee.id, toolSlug: session.toolSlug },
  }
}

function journeyLink(menteeId: string, toolSlug: string) {
  return { path: '/sessions/journey', query: { menteeId, toolSlug } }
}

// ── Recent sessions recorded by this mentor ──────────────────────────────────
const mySessions = computed(() =>
  sessionStore.sessions.filter(s => s.evaluator.id === myId.value))

const recentSessions = computed(() =>
  [...mySessions.value]
    .sort((a, b) => b.evalDate - a.evalDate || b.createdAt - a.createdAt)
    .slice(0, RECENT_LIMIT))

// ── Journeys to continue: in progress, where this mentor has recorded a visit ─
const openGapsByGroup = computed(() => {
  const counts = new Map<string, number>()
  for (const gap of gapStore.gaps) {
    if (gap.resolvedAt) continue
    counts.set(gap.evaluationGroupId, (counts.get(gap.evaluationGroupId) ?? 0) + 1)
  }
  return counts
})

const continueJourneys = computed(() => {
  const myGroups = new Set(mySessions.value.map(s => s.evaluationGroupId))
  return [...myGroups]
    .map((groupId) => {
      const sessions = sessionStore.sessionsForGroup(groupId)
      const latest = [...sessions].sort((a, b) => b.evalDate - a.evalDate || b.createdAt - a.createdAt)[0]
      if (!latest) return null
      const status = getCompetencyStatus(sessions, getToolBySlug(latest.toolSlug))
      if (status !== 'in_progress') return null
      return {
        groupId,
        menteeId: latest.mentee.id,
        menteeName: menteeName(latest),
        toolSlug: latest.toolSlug,
        toolLabel: toolLabel(latest.toolSlug),
        sessionCount: sessions.length,
        lastDate: latest.evalDate,
        openGaps: openGapsByGroup.value.get(groupId) ?? 0,
      }
    })
    .filter(journey => journey !== null)
    .sort((a, b) => b.lastDate - a.lastDate)
    .slice(0, CONTINUE_LIMIT)
})

// ── Journeys with required items never evaluated ─────────────────────────────
// "Mine" is journeys this mentor has visited. "All evaluators" lets them pick
// up a mentee a colleague started, since staff move between facilities often.
const coverageScope = ref<'mine' | 'all'>('mine')

const uncoveredJourneys = computed(() => {
  const groups = coverageScope.value === 'mine'
    ? new Set(mySessions.value.map(s => s.evaluationGroupId))
    : new Set(sessionStore.sessions.map(s => s.evaluationGroupId))
  return [...groups]
    .map((groupId) => {
      const sessions = sessionStore.sessionsForGroup(groupId)
      const latest = [...sessions].sort((a, b) => b.evalDate - a.evalDate || b.createdAt - a.createdAt)[0]
      if (!latest) return null
      const tool = getToolBySlug(latest.toolSlug)
      if (getCompetencyStatus(sessions, tool) !== 'in_progress') return null
      const notYetEvaluated = getJourneyCoverage(sessions, tool).neverRequired.length
      if (!notYetEvaluated) return null
      return {
        groupId,
        menteeId: latest.mentee.id,
        menteeName: menteeName(latest),
        toolSlug: latest.toolSlug,
        toolLabel: toolLabel(latest.toolSlug),
        notYetEvaluated,
        lastDate: latest.evalDate,
        lastEvaluator: latest.evaluator.id === myId.value
          ? 'you'
          : `${latest.evaluator.firstname} ${latest.evaluator.lastname}`.trim(),
      }
    })
    .filter(journey => journey !== null)
    .sort((a, b) => b.notYetEvaluated - a.notYetEvaluated || b.lastDate - a.lastDate)
})

// ── Gaps badge: gaps still to follow up in my journeys, or that I logged ────
const myJourneyIds = computed(() => new Set(mySessions.value.map(s => s.evaluationGroupId)))
const myOpenGaps = computed(() =>
  gapStore.gaps.filter(g => gapStatus(g) !== 'resolved'
    && (g.evaluatorId === myId.value || myJourneyIds.value.has(g.evaluationGroupId))))
const myOverdueGaps = computed(() => myOpenGaps.value.filter(g => isGapOverdue(g)).length)

// ── Sync status: say what the mentor needs to know, not just "online" ───────
const pendingCount = computed(() =>
  sessionStore.sessions.filter(s => s.syncStatus === 'pending').length
  + gapStore.gaps.filter(g => g.syncStatus === 'pending').length)

const syncLine = computed(() => {
  const pending = pendingCount.value
  const waiting = `${pending} item${pending === 1 ? '' : 's'} waiting to sync`
  if (syncStore.hasError) {
    return {
      dot: 'bg-red-500',
      text: pending ? `Sync failed · ${waiting}` : 'Sync failed',
      class: 'text-red-700 dark:text-red-400',
    }
  }
  if (!syncStore.isOnline) {
    return {
      dot: 'bg-gray-400',
      text: pending ? `Offline · ${waiting}` : 'Offline',
      class: 'text-gray-600 dark:text-gray-300',
    }
  }
  if (pending) {
    return { dot: 'bg-amber-500', text: `Syncing · ${waiting}`, class: 'text-amber-700 dark:text-amber-400' }
  }
  return { dot: 'bg-green-500', text: 'Up to date', class: 'text-green-700 dark:text-green-400' }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Greeting -->
    <div>
      <p class="text-base text-gray-500 dark:text-gray-400">{{ greeting }}</p>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5">
        {{ userStore.currentUserFullName }}
      </h1>
    </div>

    <!-- Quick actions: New Session is a guided start, My Mentees is browsing, Gaps is follow-up -->
    <div class="grid grid-cols-3 gap-3">
      <UButton
        :to="{ path: '/mentees', query: { intent: 'new-session' } }"
        color="primary"
        size="xl"
        class="h-28 flex-col justify-center rounded-2xl"
        icon="i-heroicons-plus-circle"
      >
        <span class="mt-1 text-base font-semibold">New Session</span>
        <span class="text-sm font-normal opacity-90">Choose a mentee to start</span>
      </UButton>

      <UButton
        to="/mentees"
        color="neutral"
        variant="outline"
        size="xl"
        class="h-28 flex-col justify-center rounded-2xl"
        icon="i-heroicons-users"
      >
        <span class="mt-1 text-base font-semibold">My Mentees</span>
        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">Profiles and journeys</span>
      </UButton>

      <UButton
        to="/gaps"
        color="neutral"
        variant="solid"
        size="xl"
        class="relative h-28 flex-col justify-center rounded-2xl bg-orange-600 text-white hover:bg-orange-700 active:bg-orange-800 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-700"
        icon="i-heroicons-exclamation-triangle"
      >
        <span class="mt-1 text-base font-semibold">Gaps</span>
        <span class="text-sm font-normal opacity-90">Follow up and resolve</span>
        <span
          v-if="myOpenGaps.length"
          class="absolute right-2 top-2 min-w-7 rounded-full px-2 py-0.5 text-sm font-bold"
          :class="myOverdueGaps ? 'bg-red-600 text-white ring-2 ring-white' : 'bg-white text-orange-700'"
          :aria-label="`${myOpenGaps.length} gaps to follow up${myOverdueGaps ? `, ${myOverdueGaps} overdue` : ''}`"
        >
          {{ myOpenGaps.length }}
        </span>
      </UButton>
    </div>

    <!-- Continue where you left off -->
    <section v-if="continueJourneys.length">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
        Continue where you left off
      </h2>
      <ul class="space-y-2">
        <li
          v-for="journey in continueJourneys"
          :key="journey.groupId"
          class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center"
        >
          <NuxtLink :to="journeyLink(journey.menteeId, journey.toolSlug)" class="min-w-0 flex-1">
            <p class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ journey.menteeName }}</p>
            <p class="truncate text-sm text-gray-600 dark:text-gray-300">
              {{ journey.toolLabel }} · {{ journey.sessionCount }} session{{ journey.sessionCount === 1 ? '' : 's' }}
              · last {{ formatDate(journey.lastDate) }}
            </p>
            <p v-if="journey.openGaps" class="mt-0.5 text-sm font-medium text-amber-700 dark:text-amber-400">
              {{ journey.openGaps }} open gap{{ journey.openGaps === 1 ? '' : 's' }}
            </p>
          </NuxtLink>
          <UButton
            :to="{ path: '/sessions/new', query: { menteeId: journey.menteeId, toolSlug: journey.toolSlug } }"
            color="primary"
            variant="soft"
            icon="i-heroicons-plus"
            class="shrink-0 justify-center"
          >
            Start next session
          </UButton>
        </li>
      </ul>
    </section>

    <!-- Required competencies never evaluated -->
    <section v-if="uncoveredJourneys.length || coverageScope === 'all'">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
          Competencies not yet evaluated
          <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800 dark:bg-red-900/40 dark:text-red-200">
            {{ uncoveredJourneys.length }}
          </span>
        </h2>
        <div class="flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-700">
          <UButton
            size="sm"
            :color="coverageScope === 'mine' ? 'primary' : 'neutral'"
            :variant="coverageScope === 'mine' ? 'solid' : 'ghost'"
            @click="coverageScope = 'mine'"
          >
            Mine
          </UButton>
          <UButton
            size="sm"
            :color="coverageScope === 'all' ? 'primary' : 'neutral'"
            :variant="coverageScope === 'all' ? 'solid' : 'ghost'"
            @click="coverageScope = 'all'"
          >
            All evaluators
          </UButton>
        </div>
      </div>
      <p v-if="uncoveredJourneys.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
        Every journey in progress has had each required competency evaluated at least once.
      </p>
      <ul v-else class="space-y-2">
        <li v-for="journey in uncoveredJourneys.slice(0, COVERAGE_LIMIT)" :key="journey.groupId">
          <NuxtLink
            :to="journeyLink(journey.menteeId, journey.toolSlug)"
            class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
          >
            <span class="min-w-10 shrink-0 rounded-lg bg-red-50 px-2 py-1 text-center text-base font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">
              {{ journey.notYetEvaluated }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ journey.menteeName }}</p>
              <p class="truncate text-sm text-gray-600 dark:text-gray-300">
                {{ journey.toolLabel }} · last {{ formatDate(journey.lastDate) }} by {{ journey.lastEvaluator }}
              </p>
            </div>
            <UIcon name="i-heroicons-chevron-right" class="size-5 shrink-0 text-gray-400" />
          </NuxtLink>
        </li>
      </ul>
      <p v-if="uncoveredJourneys.length > COVERAGE_LIMIT" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
        Showing the {{ COVERAGE_LIMIT }} with the most items left. The number is how many required items have no score yet.
      </p>
    </section>

    <!-- Recent sessions -->
    <section>
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
        Recent sessions
      </h2>

      <div v-if="recentSessions.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">
        <UIcon name="i-heroicons-clipboard-document-list" class="mx-auto mb-2 size-10 text-gray-300" />
        <p class="text-base">No sessions recorded yet.</p>
        <p class="mt-1 text-sm">Tap "New Session" to start.</p>
      </div>

      <ul v-else class="space-y-2">
        <li v-for="session in recentSessions" :key="session._id">
          <NuxtLink
            :to="sessionLink(session)"
            class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 transition-colors hover:border-primary-300 dark:border-gray-800 dark:bg-gray-900"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-base font-medium text-gray-900 dark:text-white">{{ menteeName(session) }}</p>
              <p class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-300">
                {{ toolLabel(session.toolSlug) }} · {{ formatDate(session.evalDate) }}
              </p>
            </div>
            <UBadge
              :label="formatPhase(session.phase)"
              :color="phaseColor(session.phase)"
              variant="soft"
              size="md"
              class="shrink-0"
            />
            <UIcon name="i-heroicons-chevron-right" class="size-5 shrink-0 text-gray-400" />
          </NuxtLink>
        </li>
      </ul>
    </section>

    <!-- Sync status -->
    <NuxtLink
      to="/sync"
      class="flex items-center justify-center gap-2 pt-2 text-sm font-medium"
      :class="syncLine.class"
    >
      <span class="size-2.5 rounded-full" :class="syncLine.dot" />
      <span>{{ syncLine.text }}</span>
      <span v-if="syncStore.lastSyncedAt" class="font-normal text-gray-500 dark:text-gray-400">
        · last sync {{ formatDate(syncStore.lastSyncedAt) }}
      </span>
    </NuxtLink>
  </div>
</template>
