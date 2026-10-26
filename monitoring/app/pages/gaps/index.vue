<script setup lang="ts">
import { format } from 'date-fns'
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { getToolBySlug } from '~/data/evaluationItemData'
import { GAP_STATUS_LABELS, gapStatus, isGapOverdue } from '~/composables/useGapStatus'
import type { GapStatus, IGapEntry } from '~/interfaces/IGapEntry'
import GapCard from '~/components/gap/GapCard.vue'
import GapForm from '~/components/gap/GapForm.vue'
import GapStatusModal from '~/components/gap/GapStatusModal.vue'

/**
 * Every gap in one place, for following up after visits: grouped by journey,
 * filtered by status, with gaps logged and moved between statuses here
 * without starting a session. "All evaluators" covers journeys handed over by
 * colleagues.
 */
const userStore = useUserStore()
const sessionStore = useSessionStore()
const gapStore = useGapStore()

const myId = computed(() => userStore.currentUser?.id)

interface JourneyInfo {
  groupId: string
  menteeId: string
  menteeName: string
  facility?: string
  toolSlug: string
  toolLabel: string
  lastDate: number
  mine: boolean
}

// Journeys from the sessions on this device. A journey is "mine" when I have
// recorded a session in it.
const journeys = computed(() => {
  const map = new Map<string, JourneyInfo>()
  for (const s of sessionStore.sessions) {
    const entry = map.get(s.evaluationGroupId)
    if (!entry) {
      map.set(s.evaluationGroupId, {
        groupId: s.evaluationGroupId,
        menteeId: s.mentee.id,
        menteeName: `${s.mentee.firstname} ${s.mentee.lastname}`.trim(),
        facility: s.mentee.facilityId || s.facilityId || undefined,
        toolSlug: s.toolSlug,
        toolLabel: getToolBySlug(s.toolSlug)?.label ?? s.toolSlug,
        lastDate: s.evalDate,
        mine: s.evaluator.id === myId.value,
      })
    } else {
      entry.lastDate = Math.max(entry.lastDate, s.evalDate)
      entry.mine ||= s.evaluator.id === myId.value
    }
  }
  return map
})

const scope = ref<'mine' | 'all'>('mine')
const statusTab = ref<GapStatus>('open')
const search = ref('')
const toolFilter = ref<string | undefined>(undefined)

function inScope(gap: IGapEntry): boolean {
  if (scope.value === 'all') return true
  return gap.evaluatorId === myId.value || !!journeys.value.get(gap.evaluationGroupId)?.mine
}

function journeyFor(gap: IGapEntry): JourneyInfo {
  const known = journeys.value.get(gap.evaluationGroupId)
  if (known) return known
  const mentee = userStore.allUsers.find(u => u.id === gap.menteeId)
  return {
    groupId: gap.evaluationGroupId,
    menteeId: gap.menteeId,
    menteeName: mentee ? `${mentee.firstname} ${mentee.lastname}` : 'Unknown mentee',
    facility: mentee?.facilityId,
    toolSlug: gap.toolSlug,
    toolLabel: getToolBySlug(gap.toolSlug)?.label ?? gap.toolSlug,
    lastDate: gap.identifiedAt,
    mine: false,
  }
}

function matchesFilters(gap: IGapEntry): boolean {
  if (toolFilter.value && gap.toolSlug !== toolFilter.value) return false
  const term = search.value.trim().toLowerCase()
  return !term || journeyFor(gap).menteeName.toLowerCase().includes(term)
}

const scopedGaps = computed(() => gapStore.gaps.filter(gap => inScope(gap) && matchesFilters(gap)))

const counts = computed(() => {
  const result: Record<GapStatus, number> = { open: 0, in_progress: 0, resolved: 0 }
  for (const gap of scopedGaps.value) result[gapStatus(gap)]++
  return result
})
const overdueCount = computed(() => scopedGaps.value.filter(gap => isGapOverdue(gap)).length)

// Gaps in the chosen status, grouped by journey. Overdue first, then oldest.
const groups = computed(() => {
  const byJourney = new Map<string, { journey: JourneyInfo; gaps: IGapEntry[] }>()
  for (const gap of scopedGaps.value) {
    if (gapStatus(gap) !== statusTab.value) continue
    const journey = journeyFor(gap)
    const group = byJourney.get(journey.groupId) ?? { journey, gaps: [] }
    group.gaps.push(gap)
    byJourney.set(journey.groupId, group)
  }
  const resolved = statusTab.value === 'resolved'
  return [...byJourney.values()]
    .map(group => ({
      ...group,
      gaps: group.gaps.sort((a, b) => resolved
        ? (b.resolvedAt ?? 0) - (a.resolvedAt ?? 0)
        : Number(isGapOverdue(b)) - Number(isGapOverdue(a)) || a.identifiedAt - b.identifiedAt),
      overdue: group.gaps.filter(gap => isGapOverdue(gap)).length,
    }))
    .sort((a, b) => resolved
      ? (b.gaps[0]?.resolvedAt ?? 0) - (a.gaps[0]?.resolvedAt ?? 0)
      : b.overdue - a.overdue || (a.gaps[0]?.identifiedAt ?? 0) - (b.gaps[0]?.identifiedAt ?? 0))
})

const toolOptions = computed(() => {
  const slugs = new Set(gapStore.gaps.map(gap => gap.toolSlug))
  return [...slugs].map(slug => ({ value: slug, label: getToolBySlug(slug)?.label ?? slug }))
    .sort((a, b) => a.label.localeCompare(b.label))
})

const emptyText = computed(() => ({
  open: 'No open gaps.',
  in_progress: 'No gaps being addressed.',
  resolved: 'No resolved gaps yet.',
})[statusTab.value])

// ── Logging a gap without a session ────────────────────────────────────────
const showJourneyPicker = ref(false)
const pickerSearch = ref('')
const formJourney = ref<JourneyInfo | null>(null)
const showGapForm = ref(false)

const pickableJourneys = computed(() => {
  const term = pickerSearch.value.trim().toLowerCase()
  return [...journeys.value.values()]
    .filter(j => (scope.value === 'all' || j.mine) && (!term || j.menteeName.toLowerCase().includes(term) || j.toolLabel.toLowerCase().includes(term)))
    .sort((a, b) => b.lastDate - a.lastDate)
})

function logGapFor(journey: JourneyInfo) {
  formJourney.value = journey
  showJourneyPicker.value = false
  // Let the picker close before the drawer opens.
  nextTick(() => { showGapForm.value = true })
}

// ── Moving a gap between statuses ──────────────────────────────────────────
const statusGap = ref<IGapEntry | null>(null)
const showStatusModal = ref(false)
function openStatus(gap: IGapEntry) {
  statusGap.value = gap
  showStatusModal.value = true
}

const formatDate = (ts: number) => format(new Date(ts), 'd MMM yyyy')
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gaps</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300">Follow up gaps after visits, log new ones and record when they are resolved.</p>
      </div>
      <UButton color="warning" size="lg" icon="i-heroicons-plus" @click="showJourneyPicker = true">Log a gap</UButton>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-700">
        <UButton size="sm" :color="scope === 'mine' ? 'primary' : 'neutral'" :variant="scope === 'mine' ? 'solid' : 'ghost'" @click="scope = 'mine'">Mine</UButton>
        <UButton size="sm" :color="scope === 'all' ? 'primary' : 'neutral'" :variant="scope === 'all' ? 'solid' : 'ghost'" @click="scope = 'all'">All evaluators</UButton>
      </div>
      <UInput v-model="search" icon="i-heroicons-magnifying-glass" placeholder="Search mentee" class="min-w-48 flex-1" />
      <USelect v-model="toolFilter" :items="toolOptions" placeholder="All tools" class="min-w-44" />
      <UButton v-if="toolFilter" size="sm" variant="link" color="neutral" @click="toolFilter = undefined">Clear tool</UButton>
    </div>

    <!-- Status tabs -->
    <div class="grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800" role="tablist" aria-label="Gap status">
      <button
        v-for="tab in (['open', 'in_progress', 'resolved'] as const)"
        :key="tab"
        type="button"
        role="tab"
        :aria-selected="statusTab === tab"
        class="min-h-11 rounded-md px-2 py-2 text-sm font-semibold"
        :class="statusTab === tab ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
        @click="statusTab = tab"
      >
        {{ GAP_STATUS_LABELS[tab] }} ({{ counts[tab] }})
      </button>
    </div>

    <p v-if="overdueCount && statusTab !== 'resolved'" class="flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-200">
      <UIcon name="i-heroicons-clock" class="size-5 shrink-0" />
      {{ overdueCount }} gap{{ overdueCount === 1 ? ' is' : 's are' }} past the "address by" date.
    </p>

    <div v-if="groups.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">
      <UIcon name="i-heroicons-clipboard-document-check" class="mx-auto mb-2 size-10 text-gray-300" />
      <p class="text-base">{{ emptyText }}</p>
      <p v-if="scope === 'mine'" class="mt-1 text-sm">Switch to All evaluators to see gaps from colleagues' journeys.</p>
    </div>

    <section v-for="group in groups" :key="group.journey.groupId" class="space-y-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <NuxtLink :to="{ path: '/sessions/journey', query: { menteeId: group.journey.menteeId, toolSlug: group.journey.toolSlug } }" class="min-w-0">
          <p class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ group.journey.menteeName }}</p>
          <p class="truncate text-sm text-gray-600 dark:text-gray-300">
            {{ group.journey.toolLabel }}<template v-if="group.journey.facility"> · {{ group.journey.facility }}</template>
            · last visit {{ formatDate(group.journey.lastDate) }}
          </p>
        </NuxtLink>
        <UButton size="sm" variant="soft" color="warning" icon="i-heroicons-plus" @click="logGapFor(group.journey)">Log a gap</UButton>
      </div>
      <GapCard v-for="gap in group.gaps" :key="gap._id" :gap="gap" @status="openStatus" />
    </section>

    <!-- Choose the journey for a new gap -->
    <UModal v-model:open="showJourneyPicker" title="Log a gap" description="Choose the mentee and tool the gap belongs to.">
      <template #body>
        <div class="space-y-3">
          <UInput v-model="pickerSearch" icon="i-heroicons-magnifying-glass" placeholder="Search mentee or tool" class="w-full" />
          <p v-if="scope === 'mine'" class="text-sm text-gray-500 dark:text-gray-400">Showing your journeys. Switch to All evaluators on the page to choose any journey.</p>
          <ul class="max-h-[50vh] space-y-1.5 overflow-y-auto">
            <li v-for="journey in pickableJourneys" :key="journey.groupId">
              <button
                type="button"
                class="flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 text-left hover:border-primary-400 dark:border-gray-700"
                @click="logGapFor(journey)"
              >
                <span class="min-w-0">
                  <span class="block truncate font-semibold text-gray-900 dark:text-white">{{ journey.menteeName }}</span>
                  <span class="block truncate text-sm text-gray-600 dark:text-gray-300">{{ journey.toolLabel }} · last visit {{ formatDate(journey.lastDate) }}</span>
                </span>
                <UIcon name="i-heroicons-chevron-right" class="size-5 shrink-0 text-gray-400" />
              </button>
            </li>
            <li v-if="pickableJourneys.length === 0" class="py-6 text-center text-sm text-gray-500">No journeys match.</li>
          </ul>
        </div>
      </template>
    </UModal>

    <GapForm
      v-if="userStore.currentUser && formJourney"
      v-model:open="showGapForm"
      :evaluation-group-id="formJourney.groupId"
      :mentee-id="formJourney.menteeId"
      :evaluator-id="userStore.currentUser.id"
      :tool-slug="formJourney.toolSlug"
    />

    <GapStatusModal v-model:open="showStatusModal" :gap="statusGap" />
  </div>
</template>
