<script setup lang="ts">
import { useSessionStore } from '~/stores/sessionStore'
import { getToolBySlug } from '~/data/evaluationItemData'
import JourneySummaryCard from '~/components/journey/SummaryCard.vue'
import JourneySessionCard from '~/components/journey/SessionCard.vue'

const route = useRoute()
const router = useRouter()
const sessionStore = useSessionStore()

const menteeId = computed(() => route.query.menteeId as string)
const toolSlug = computed(() => route.query.toolSlug as string)

const tool = computed(() => getToolBySlug(toolSlug.value ?? ''))

await sessionStore.loadAll()

const sessions = computed(() => {
  if (!menteeId.value || !toolSlug.value) return []
  const groupId = `${menteeId.value}::${toolSlug.value}`
  return sessionStore.sessions
    .filter(s => s.evaluationGroupId === groupId)
    .sort((a, b) => b.evalDate - a.evalDate)
})

const averageScore = computed(() => {
  if (sessions.value.length === 0) return '0'
  const latestSession = sessions.value[0]
  if (!latestSession) return '0'
  const toolScores = latestSession.itemScores?.filter(s => !s.itemSlug.startsWith('counselling')) ?? []
  if (toolScores.length === 0) return '0'
  const scored = toolScores.filter(s => s.menteeScore !== null)
  if (scored.length === 0) return '0'
  const sum = scored.reduce((acc, s) => acc + (s.menteeScore ?? 0), 0)
  return (sum / scored.length).toFixed(1)
})

const latestDate = computed(() => {
  if (sessions.value.length === 0) return 'N/A'
  const session = sessions.value[0]
  if (!session?.evalDate) return 'N/A'
  return formatDate(session.evalDate)
})

function formatDate(ts: number | null | undefined): string {
  if (!ts) return 'N/A'
  return new Date(ts).toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-10">
      <div class="max-w-2xl mx-auto px-4 py-3">
        <div class="flex items-center gap-3">
          <UButton
            variant="soft"
            color="neutral"
            size="sm"
            @click="router.back()"
          >
            <UIcon name="i-heroicons-arrow-left" class="w-4 h-4" />
          </UButton>
          <div class="min-w-0">
            <h1 class="font-semibold text-gray-900 dark:text-white truncate">{{ tool?.label }}</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ sessions.length }} session{{ sessions.length !== 1 ? 's' : '' }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-4 space-y-4">
      <!-- Summary Card -->
      <JourneySummaryCard
        v-if="sessions.length > 0"
        :total-sessions="sessions.length"
        :average-score="averageScore"
        :latest-date="latestDate"
      />

      <!-- Sessions List -->
      <div v-if="sessions.length > 0" class="space-y-3">
        <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          Sessions (sorted by date)
        </h2>

        <div
          v-for="(session, index) in sessions"
          :key="session._id"
          class="cursor-pointer"
          @click="router.push(`/sessions/session?sessionId=${session._id}&menteeId=${menteeId}&toolSlug=${toolSlug}`)"
        >
          <JourneySessionCard
            :session-number="sessions.length - index"
            :date="formatDate(session.evalDate) ?? 'N/A'"
            :phase="session.phase ?? 'unknown'"
          />
        </div>
      </div>

      <!-- No sessions -->
      <div v-else class="text-center py-10 text-gray-400">
        <UIcon name="i-heroicons-clipboard-document-list" class="size-10 mx-auto mb-2 text-gray-300" />
        <p class="text-sm">No sessions recorded for this tool yet.</p>
      </div>
    </div>
  </div>
</template>