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
            :to="`/sessions/new?menteeId=${route.params.id}&toolSlug=${journey.toolSlug}`"
            class="block bg-white dark:bg-gray-900 rounded-xl px-4 py-3.5 shadow-sm border border-gray-100 dark:border-gray-800 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-medium text-gray-900 dark:text-white">{{ journey.toolLabel }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  {{ journey.sessionCount }} session{{ journey.sessionCount !== 1 ? 's' : '' }}
                  · Last: {{ formatDate(journey.lastSessionDate) }}
                </p>
              </div>
              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <UBadge
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
              class="w-full flex items-center justify-between px-4 py-3.5 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors text-left"
              @click="startSession(tool.slug)"
            >
              <div>
                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ tool.label }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ tool.items.length }} items</p>
              </div>
              <UIcon
                v-if="hasJourney(tool.slug)"
                name="i-heroicons-arrow-path"
                class="size-4 text-primary-500 shrink-0"
                title="Continue existing journey"
              />
              <UIcon v-else name="i-heroicons-plus-circle" class="size-4 text-gray-400 shrink-0" />
            </button>
          </li>
        </ul>
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

const route = useRoute()
const router = useRouter()
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
  latestPhase: MentorshipPhase | null
  openGaps: number
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
    const sorted = [...sessions].sort((a, b) => b.evalDate - a.evalDate)
    const toolSlug = sessions[0]!.toolSlug
    const tool = evaluationTools.find(t => t.slug === toolSlug)
    const openGaps = gapStore.gaps.filter(
      g => g.evaluationGroupId === groupId && !g.resolvedAt
    ).length

    return {
      groupId,
      toolSlug,
      toolLabel: tool?.label ?? toolSlug,
      sessionCount: sessions.length,
      lastSessionDate: sorted[0]!.evalDate,
      latestPhase: sorted[0]!.phase,
      openGaps,
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
  showToolPicker.value = false
  router.push(`/sessions/new?menteeId=${menteeId.value}&toolSlug=${toolSlug}`)
}
</script>
