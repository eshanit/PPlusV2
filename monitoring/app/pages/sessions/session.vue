<script setup lang="ts">
import { useSessionStore } from '~/stores/sessionStore'
import { getToolBySlug } from '~/data/evaluationItemData'
import { calculateScoreCounts, calculateAverage, formatDate } from '~/composables/useSessionCalculations'
import ScoreLegend from '~/components/session/ScoreLegend.vue'
import ScoreDistribution from '~/components/session/ScoreDistribution.vue'
import ItemScoresList from '~/components/session/ItemScoresList.vue'

const route = useRoute()
const router = useRouter()
const sessionStore = useSessionStore()

const sessionId = computed(() => route.query.sessionId as string)
const menteeId = computed(() => route.query.menteeId as string)
const toolSlug = computed(() => route.query.toolSlug as string)

const tool = computed(() => getToolBySlug(toolSlug.value ?? ''))
const toolItems = computed(() => tool.value?.items ?? [])

await sessionStore.loadAll()

const session = computed(() => {
  return sessionStore.sessions.find(s => s._id === sessionId.value)
})

const toolItemScores = computed(() => {
  if (!session.value) return []
  return session.value.itemScores.filter(s => !s.itemSlug.startsWith('counselling'))
})

const counsellingItemScores = computed(() => {
  if (!session.value) return []
  return session.value.itemScores.filter(s => s.itemSlug.startsWith('counselling'))
})

const scoreCounts = computed(() => calculateScoreCounts(toolItemScores.value))
const averageScore = computed(() => calculateAverage(toolItemScores.value))

const itemMetadata = computed(() => toolItems.value.map(item => ({
  slug: item.slug,
  number: item.number,
  title: item.title,
})))
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
            <h1 class="font-semibold text-gray-900 dark:text-white truncate">Session Details</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(session?.evalDate) }}</p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="session" class="max-w-2xl mx-auto px-4 py-4 space-y-6">
      <!-- Summary Card -->
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
        <div class="grid grid-cols-3 gap-4 text-center">
          <div>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ toolItemScores.length }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Items</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ averageScore }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Average</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ session.phase }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Phase</p>
          </div>
        </div>
      </div>

      <!-- Score Legend -->
      <ScoreLegend />

      <!-- Score Distribution -->
      <ScoreDistribution :counts="scoreCounts" />

      <!-- Tool Items -->
      <ItemScoresList
        :title="tool?.label ?? ''"
        :items="toolItemScores"
        :item-metadata="itemMetadata"
      />

      <!-- Counselling Items -->
      <ItemScoresList
        v-if="counsellingItemScores.length > 0"
        title="General Counselling Competencies"
        :items="counsellingItemScores"
        :item-metadata="itemMetadata"
        color-class="bg-orange-50 dark:bg-orange-900/20 border-orange-100 dark:border-orange-800"
      />

      <!-- Session Notes -->
      <div v-if="session.notes" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
          Session Notes
        </h3>
        <p class="text-sm text-gray-700 dark:text-gray-300 italic">
          {{ session.notes }}
        </p>
      </div>
    </div>

    <!-- No session -->
    <div v-else class="text-center py-10 text-gray-400">
      <UIcon name="i-heroicons-clipboard-document-list" class="size-10 mx-auto mb-2 text-gray-300" />
      <p class="text-sm">Session not found.</p>
    </div>
  </div>
</template>