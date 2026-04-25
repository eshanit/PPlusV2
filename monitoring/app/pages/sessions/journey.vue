<script setup lang="ts">
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { useUserStore } from '~/stores/userStore'
import { getToolBySlug } from '~/data/evaluationItemData'
import { useCompetency } from '~/composables/useCompetency'
import type { IGapEntry } from '~/interfaces/IGapEntry'
import JourneySummaryCard from '~/components/journey/SummaryCard.vue'
import JourneySessionCard from '~/components/journey/SessionCard.vue'
import GapCard from '~/components/gap/GapCard.vue'
import GapForm from '~/components/gap/GapForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const sessionStore = useSessionStore()
const gapStore = useGapStore()
const userStore = useUserStore()

const menteeId = computed(() => route.query.menteeId as string)
const toolSlug = computed(() => route.query.toolSlug as string)

const tool = computed(() => getToolBySlug(toolSlug.value ?? ''))

await Promise.all([sessionStore.loadAll(), gapStore.loadAll()])

const evaluationGroupId = computed(() =>
  menteeId.value && toolSlug.value ? `${menteeId.value}::${toolSlug.value}` : '',
)

const sessions = computed(() => {
  if (!evaluationGroupId.value) return []
  return sessionStore.sessions
    .filter(s => s.evaluationGroupId === evaluationGroupId.value)
    .sort((a, b) => b.evalDate - a.evalDate)
})

const { status: competencyStatus, isBasicCompetent } = useCompetency(
  computed(() => [...sessions.value].sort((a, b) => a.evalDate - b.evalDate || a.createdAt - b.createdAt)),
  tool,
)

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

// Gaps
const gaps = computed(() =>
  evaluationGroupId.value ? gapStore.gapsForGroup(evaluationGroupId.value) : [],
)
const openGaps = computed(() => gaps.value.filter(g => !g.resolvedAt))
const resolvedGaps = computed(() => gaps.value.filter(g => g.resolvedAt))

const showGapForm = ref(false)

const resolvingGap = ref<IGapEntry | null>(null)
const resolveNote = ref('')
const resolving = ref(false)
const showResolveModal = ref(false)

function openResolve(gap: IGapEntry) {
  resolvingGap.value = gap
  resolveNote.value = ''
  showResolveModal.value = true
}

async function confirmResolve() {
  if (!resolvingGap.value) return
  resolving.value = true
  try {
    const now = Date.now()
    await gapStore.save({
      ...resolvingGap.value,
      resolvedAt: now,
      resolutionNote: resolveNote.value.trim() || undefined,
      updatedAt: now,
    })
    toast.add({ title: 'Gap resolved', color: 'success', icon: 'i-heroicons-check-circle' })
    showResolveModal.value = false
  } catch (err) {
    toast.add({ title: 'Save failed', description: String(err), color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    resolving.value = false
  }
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

      <!-- Competency banner -->
      <div
        v-if="isBasicCompetent"
        class="rounded-xl px-4 py-3 flex items-start gap-3"
        :class="competencyStatus === 'fully_competent'
          ? 'bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800'
          : 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'"
      >
        <UIcon
          name="i-heroicons-check-badge"
          class="size-5 shrink-0 mt-0.5"
          :class="competencyStatus === 'fully_competent' ? 'text-teal-600 dark:text-teal-400' : 'text-green-600 dark:text-green-400'"
        />
        <div>
          <p
            class="font-semibold text-sm"
            :class="competencyStatus === 'fully_competent' ? 'text-teal-800 dark:text-teal-200' : 'text-green-800 dark:text-green-200'"
          >
            {{ competencyStatus === 'fully_competent' ? 'Fully Competent' : 'Basic Competency Reached' }}
          </p>
          <p
            class="text-xs mt-0.5"
            :class="competencyStatus === 'fully_competent' ? 'text-teal-700 dark:text-teal-300' : 'text-green-700 dark:text-green-300'"
          >
            {{
              competencyStatus === 'fully_competent'
                ? 'All items (including advanced) scored 4 or 5. This journey is complete.'
                : 'All core items scored 4 or 5. No further sessions can be added for this tool.'
            }}
          </p>
        </div>
      </div>

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

      <!-- Gaps section -->
      <div class="pt-2">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
            Gaps
            <UBadge
              v-if="openGaps.length > 0"
              :label="String(openGaps.length)"
              color="warning"
              variant="solid"
              size="xs"
            />
          </h2>
          <UButton
            size="xs"
            variant="soft"
            color="warning"
            icon="i-heroicons-plus"
            @click="showGapForm = true"
          >
            Log Gap
          </UButton>
        </div>

        <div v-if="gaps.length > 0" class="space-y-2">
          <GapCard
            v-for="gap in gaps"
            :key="gap._id"
            :gap="gap"
            @resolve="openResolve"
          />
        </div>
        <div v-else class="text-center py-6 text-gray-400 text-sm">
          No gaps logged for this journey.
        </div>
      </div>

      <div class="pb-8" />
    </div>

    <!-- Gap form modal -->
    <GapForm
      v-if="userStore.currentUser"
      v-model:open="showGapForm"
      :evaluation-group-id="evaluationGroupId"
      :mentee-id="menteeId"
      :evaluator-id="userStore.currentUser.id"
      :tool-slug="toolSlug"
    />

    <!-- Resolve modal -->
    <UModal v-model:open="showResolveModal" title="Resolve Gap">
      <template #body>
        <div class="space-y-4">
          <p class="text-sm text-gray-700 dark:text-gray-300">{{ resolvingGap?.description }}</p>
          <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
              Resolution Note <span class="text-gray-400 font-normal normal-case tracking-normal">(optional)</span>
            </label>
            <UTextarea
              v-model="resolveNote"
              placeholder="How was this gap addressed?"
              :rows="2"
              class="w-full"
            />
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex gap-2 justify-end">
          <UButton variant="ghost" color="neutral" @click="showResolveModal = false">Cancel</UButton>
          <UButton color="success" :loading="resolving" @click="confirmResolve">Mark Resolved</UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>