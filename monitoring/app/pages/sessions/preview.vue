<script setup lang="ts">
import { useSessionStore } from '~/stores/sessionStore'
import { useUserStore } from '~/stores/userStore'
import { getToolBySlug, counsellingTool } from '~/data/evaluationItemData'
import type { MentorshipPhase } from '~/interfaces/ISession'
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
}

interface PreviewData {
  menteeId: string
  menteeName: string
  toolSlug: string
  toolLabel: string
  items: PreviewItem[]
  phase: MentorshipPhase | null
  evalDate: string
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

const averageScore = computed(() => {
  if (!previewData.value) return '0'
  const items = previewData.value.items.map(i => ({ itemSlug: i.slug, menteeScore: i.score, notes: i.notes }))
  return calculateAverage(items)
})

const naCount = computed(() => {
  if (!previewData.value) return 0
  return previewData.value.items.filter(item => item.score === null).length
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
    .filter(i => i.type === 'tool')
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

    const toolItems = pData.items.filter(i => i.type === 'tool')
    const counsellingItems = pData.items.filter(i => i.type === 'counselling')

    await sessionStore.save({
      _id: '',
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
      phase: (phase.value ?? null) as MentorshipPhase | null,
      notes: notes.value.trim() || undefined,
      syncStatus: 'pending',
      createdAt: now,
      updatedAt: now,
    })

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
    .filter(i => i.type === 'tool')
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
    .filter(i => i.type === 'counselling')
    .map(item => ({
      itemSlug: item.slug,
      menteeScore: item.score,
      notes: item.notes,
    }))
})

const counsellingItemMetadata = computed(() =>
  counsellingTool.items.map(item => ({ slug: item.slug, number: item.number, title: item.title }))
)

function goBack() {
  router.back()
}

const isValid = computed(() => phase.value !== null && !!evalDateStr.value)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-10">
      <div class="max-w-2xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <UButton
              variant="soft"
              color="neutral"
              size="sm"
              @click="goBack"
            >
              <UIcon name="i-heroicons-arrow-left" class="w-4 h-4" />
            </UButton>
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
          </div>
        </div>
      </div>
    </div>

    <div v-if="previewData" class="max-w-2xl mx-auto px-4 py-4 space-y-6">
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

      <!-- Session Details -->
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1">
              Evaluation Date
            </label>
            <UInput type="date" v-model="evalDateStr" class="w-full" />
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

      <!-- Tool Items using reusable component -->
      <ItemScoresList
        :title="previewData.toolLabel"
        :items="toolItemsData"
        :item-metadata="toolItemMetadata"
      />

      <!-- Counselling Items using reusable component -->
      <ItemScoresList
        title="General Counselling Competencies"
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
          size="lg"
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