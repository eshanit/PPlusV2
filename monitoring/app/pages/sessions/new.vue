<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { getToolBySlug, counsellingTool } from '~/data/evaluationItemData'
import type { MentorshipPhase } from '~/interfaces/ISession'
import type { IEvalItem } from '~/interfaces/IEvalItem'

definePageMeta({
  middleware: [(to) => {
    if (!to.query.menteeId || !to.query.toolSlug) return navigateTo('/')
  }],
})

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const sessionStore = useSessionStore()
const toast = useToast()

const menteeId = computed(() => route.query.menteeId as string | undefined)
const toolSlug = computed(() => route.query.toolSlug as string | undefined)

const tool = computed(() => getToolBySlug(toolSlug.value ?? ''))
const mentee = computed(() => userStore.allUsers.find(u => u.id === menteeId.value))
const menteeName = computed(() =>
  mentee.value ? `${mentee.value.firstname} ${mentee.value.lastname}` : '…'
)

const phase = ref<MentorshipPhase | null>(null)
const evalDateStr = ref(new Date().toISOString().split('T')[0]!)
const notes = ref('')
const saving = ref(false)

const currentIndex = ref(0)
const swiperRef = ref<HTMLElement>()

const allItems = computed(() => {
  if (!tool.value) return []
  return [
    ...tool.value.items.map(item => ({ ...item, type: 'tool' as const })),
    ...counsellingTool.items.map(item => ({ ...item, type: 'counselling' as const }))
  ]
})

const currentItem = computed(() => allItems.value[currentIndex.value])
const totalItems = computed(() => allItems.value.length)
const isLastItem = computed(() => currentIndex.value === totalItems.value - 1)
const isFirstItem = computed(() => currentIndex.value === 0)
const progress = computed(() => `${currentIndex.value + 1} / ${totalItems.value}`)

const itemScores = reactive<Record<string, number | null>>({})
const counsellingScores = reactive<Record<string, number | null>>({})

function setScore(slug: string, value: number | null, type: string) {
  if (type === 'counselling') {
    counsellingScores[slug] = value
  } else {
    itemScores[slug] = value
  }
}

function getScore(slug: string, type: string): number | null {
  return type === 'counselling' 
    ? (counsellingScores[slug] ?? null)
    : (itemScores[slug] ?? null)
}

function next() {
  if (currentIndex.value < totalItems.value - 1) {
    currentIndex.value++
    scrollToCurrent()
  }
}

function prev() {
  if (currentIndex.value > 0) {
    currentIndex.value--
    scrollToCurrent()
  }
}

function scrollToCurrent() {
  if (swiperRef.value) {
    swiperRef.value.scrollTo({ left: 0, behavior: 'smooth' })
  }
}

function goToItem(index: number) {
  if (index >= 0 && index < totalItems.value) {
    currentIndex.value = index
  }
}

function getItemType(slug: string): string {
  return slug.startsWith('counselling-') ? 'counselling' : 'tool'
}

const isValid = computed(() => phase.value !== null && !!evalDateStr.value)

async function save() {
  if (!isValid.value || !mentee.value || !tool.value || !userStore.currentUser) return
  saving.value = true
  try {
    const now = Date.now()
    const evalDate = new Date(evalDateStr.value + 'T12:00:00').getTime()
    const evaluationGroupId = `${mentee.value.id}::${tool.value.slug}`

    await sessionStore.save({
      _id: '',
      type: 'session',
      evaluationGroupId,
      mentee: mentee.value,
      evaluator: userStore.currentUser,
      toolSlug: tool.value.slug,
      evalDate,
      facilityId: mentee.value.facilityId ?? '',
      districtId: mentee.value.districtId ?? '',
      itemScores: tool.value.items.map(item => ({
        itemSlug: item.slug,
        menteeScore: (itemScores[item.slug] ?? null) as 1 | 2 | 3 | 4 | 5 | null,
      })),
      counsellingScores: counsellingTool.items.map(item => ({
        itemSlug: item.slug,
        menteeScore: (counsellingScores[item.slug] ?? null) as 1 | 2 | 3 | 4 | 5 | null,
      })),
      phase: phase.value,
      notes: notes.value.trim() || undefined,
      syncStatus: 'pending',
      createdAt: now,
      updatedAt: now,
    })

    toast.add({ title: 'Session saved', color: 'success', icon: 'i-heroicons-check-circle' })
    router.push(`/mentees/${mentee.value.id}`)
  } catch (err) {
    toast.add({ title: 'Save failed', description: String(err), color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <SessionSaveBar
      :mentee-name="menteeName"
      :tool-label="tool?.label"
      :saving="saving"
      :is-valid="isValid"
      @save="save"
    />

    <!-- Progress bar -->
    <div class="sticky top-0 bg-white dark:bg-gray-900 z-10 border-b border-gray-100 dark:border-gray-800">
      <div class="flex items-center justify-between px-4 py-2">
        <UButton
          variant="ghost"
          size="xs"
          :disabled="isFirstItem"
          @click="prev"
        >
          <UIcon name="i-heroicons-chevron-left" class="w-4 h-4" />
        </UButton>
        
        <div class="text-center">
          <p class="text-xs font-medium text-gray-900 dark:text-white">{{ progress }}</p>
          <p class="text-xs text-gray-500">{{ currentItem?.title }}</p>
        </div>
        
        <UButton
          variant="ghost"
          size="xs"
          :disabled="isLastItem"
          @click="next"
        >
          <UIcon name="i-heroicons-chevron-right" class="w-4 h-4" />
        </UButton>
      </div>
      
      <!-- Progress dots -->
      <div class="flex justify-center gap-1 px-4 pb-3">
        <button
          v-for="(item, idx) in allItems"
          :key="item.slug"
          class="w-2 h-2 rounded-full transition-colors"
          :class="idx === currentIndex ? 'bg-primary-500' : getScore(item.slug, getItemType(item.slug)) ? 'bg-primary-300' : 'bg-gray-200 dark:bg-gray-700'"
          @click="goToItem(idx)"
        />
      </div>
    </div>

    <div class="space-y-6 py-5">

      <SessionPhaseSelector v-model="phase" />

      <section>
        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
          Evaluation Date <span class="text-red-500">*</span>
        </label>
        <UInput type="date" v-model="evalDateStr" class="max-w-xs" />
      </section>

      <!-- Current Item Card -->
      <div v-if="currentItem" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
        <div class="flex items-start gap-3">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-white text-sm"
            :class="currentItem.type === 'counselling' ? 'bg-orange-500' : 'bg-primary-500'"
          >
            {{ currentItem.number }}
          </div>
          <div class="flex-1">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
              {{ currentItem.type === 'counselling' ? 'General Counselling' : currentItem.category }}
            </p>
            <h3 class="font-medium text-gray-900 dark:text-white mb-4">
              {{ currentItem.title }}
            </h3>
            
            <!-- Score buttons -->
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="score in [1, 2, 3, 4, 5, null]"
                :key="score ?? 'na'"
                class="p-3 rounded-lg text-center text-sm font-medium transition-all"
                :class="getScore(currentItem.slug, getItemType(currentItem.slug)) === score
                  ? score === 1 ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 border-2 border-red-500'
                  : score === 2 ? 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300 border-2 border-orange-500'
                  : score === 3 ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border-2 border-blue-500'
                  : score === 4 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border-2 border-green-500'
                  : score === 5 ? 'bg-teal-100 dark:bg-teal-900 text-teal-700 dark:text-teal-300 border-2 border-teal-500'
                  : 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-2 border-gray-200 dark:border-gray-700'
                  : 'bg-gray-50 dark:bg-gray-800 text-gray-400 border-2 border-gray-100 dark:border-gray-700 hover:border-gray-300'"
                @click="setScore(currentItem.slug, score, getItemType(currentItem.slug))"
              >
                {{ score ?? 'N/A' }}
              </button>
            </div>
            
            <p class="text-xs text-gray-500 mt-3 text-center">
              1=Absent, 2=Basic, 3=Satisfactory, 4=Good, 5=Excellent, N/A=Not evaluated
            </p>
          </div>
        </div>
      </div>

      <!-- Navigation buttons -->
      <div class="flex gap-3">
        <UButton
          variant="soft"
          color="neutral"
          block
          :disabled="isFirstItem"
          @click="prev"
        >
          <UIcon name="i-heroicons-chevron-left" class="w-4 h-4 mr-2" />
          Previous
        </UButton>
        
        <UButton
          v-if="!isLastItem"
          color="primary"
          block
          @click="next"
        >
          Next
          <UIcon name="i-heroicons-chevron-right" class="w-4 h-4 ml-2" />
        </UButton>
        
        <UButton
          v-else
          color="green"
          block
          :loading="saving"
          @click="save"
        >
          Save Session
          <UIcon name="i-heroicons-check" class="w-4 h-4 ml-2" />
        </UButton>
      </div>

      <section>
        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
          Notes
        </label>
        <UTextarea
          v-model="notes"
          placeholder="Any observations or comments…"
          :rows="3"
        />
      </section>
    </div>
  </div>
</template>