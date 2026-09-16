<script setup lang="ts">
import { format } from 'date-fns'
import { onKeyStroke } from '@vueuse/core'
import { useUserStore } from '~/stores/userStore'
import { useSessionStore } from '~/stores/sessionStore'
import { getToolBySlug, counsellingTool } from '~/data/evaluationItemData'
import type { MentorshipPhase } from '~/interfaces/ISession'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import SessionItemCard from '~/components/session/ItemCard.vue'
import SessionProgressBar from '~/components/session/ProgressBar.vue'
import SessionRoundSelector, { type RoundOption } from '~/components/session/RoundSelector.vue'

definePageMeta({
  middleware: [(to) => {
    if (!to.query.menteeId || !to.query.toolSlug) {
      return navigateTo('/')
    }
  }],
})

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const sessionStore = useSessionStore()
const toast = useToast()

// Load users and sessions if not already loaded
await Promise.all([
  userStore.loadUsers(),
  sessionStore.loadAll(),
])

const menteeId = computed(() => route.query.menteeId as string | undefined)
const toolSlug = computed(() => route.query.toolSlug as string | undefined)

const tool = computed(() => getToolBySlug(toolSlug.value ?? ''))
const mentee = computed(() => userStore.allUsers.find(u => u.id === menteeId.value))
const menteeName = computed(() =>
  mentee.value ? `${mentee.value.firstname} ${mentee.value.lastname}` : 'Unknown Mentee'
)

// Generate evaluationGroupId for previous scores lookup
const evaluationGroupId = computed(() => {
  if (!mentee.value || !tool.value) return ''
  return `${mentee.value.id}::${tool.value.slug}`
})

const totalPreviousSessions = computed(() => {
  return sessionStore.getSessionCount(evaluationGroupId.value)
})

const phase = ref<MentorshipPhase | null>(null)
const evalDateStr = ref(format(new Date(), 'yyyy-MM-dd'))
const notes = ref('')
const saving = ref(false)

const evalDateForRounds = computed(() => new Date(evalDateStr.value + 'T12:00:00').getTime())

// Rounds already recorded for the picked date, before this new one is saved.
const roundsRecordedToday = computed(() => {
  if (!evaluationGroupId.value) return 0
  return sessionStore.roundsForDay(evaluationGroupId.value, evalDateForRounds.value)
})

// Round options for the picked date: every round already recorded (disabled,
// to prevent accidental duplicates) plus open slots to pick ahead. Always
// offers at least MIN_ROUND_OPTIONS slots — even on a brand-new day with
// nothing recorded yet — so a mentor typing up paper notes out of order can
// mark their very first app entry as "Round 3" without having to enter
// rounds 1 and 2 first.
const MIN_ROUND_OPTIONS = 5

const roundOptions = computed((): RoundOption[] => {
  const taken = evaluationGroupId.value
    ? sessionStore.takenRoundsForDay(evaluationGroupId.value, evalDateForRounds.value)
    : new Set<number>()
  const highestTaken = taken.size > 0 ? Math.max(...taken) : 0
  const total = Math.max(highestTaken + 1, MIN_ROUND_OPTIONS)
  return Array.from({ length: total }, (_, i) => i + 1).map(value => ({
    value,
    disabled: taken.has(value),
  }))
})

const roundOfDay = ref<number | undefined>(undefined)

// Default to the smallest open round whenever the date (or existing same-day
// sessions) changes — fills gaps left by out-of-order paper transcription
// before ever suggesting a brand-new round.
watch(roundOptions, (options) => {
  if (roundOfDay.value != null && options.some(o => o.value === roundOfDay.value && !o.disabled)) {
    return
  }
  roundOfDay.value = options.find(o => !o.disabled)?.value
}, { immediate: true })

const currentIndex = ref(0)

// Combine tool items and counselling items
const allItems = computed(() => {
  if (!tool.value) return []
  return [
    ...tool.value.items.map(item => ({ ...item, type: 'tool' as const })),
    ...counsellingTool.items.map(item => ({ ...item, type: 'counselling' as const }))
  ]
})

const currentItem = computed(() => allItems.value[currentIndex.value])

function getItemType(slug: string): string {
  return slug.startsWith('counselling-') ? 'counselling' : 'tool'
}

// Current scores map (combining tool and counselling)
const currentScores = computed(() => {
  const scores: Record<string, number | null> = {}
  for (const item of allItems.value) {
    if (item.type === 'counselling') {
      scores[item.slug] = (counsellingScores as any)[item.slug] ?? null
    } else {
      scores[item.slug] = (itemScores as any)[item.slug] ?? null
    }
  }
  return scores
})

// Check if current item has been scored (or N/A with notes)
const currentItemScored = computed(() => {
  if (!currentItem.value?.slug) return false
  const score = currentScores.value[currentItem.value.slug]
  if (score !== null) return true
  if (score === null && isNASelected.value) {
    const notes = getCurrentItemNotes()
    return !!(notes && notes.trim())
  }
  return false
})

function getCurrentNASelected(): boolean {
  if (!currentItem.value?.slug) return false
  const type = getItemType(currentItem.value.slug)
  return type === 'counselling'
    ? !!(counsellingNASelected[currentItem.value.slug])
    : !!(itemNASelected[currentItem.value.slug])
}

const isNASelected = computed(() => getCurrentNASelected())

// Check if all items have been scored (or N/A with notes)
const allItemsScored = computed(() => {
  return allItems.value.every(item => {
    const score = currentScores.value[item.slug]
    if (score !== null) return true
    const notes = itemNotes[item.slug]
    return !!(notes && notes.trim())
  })
})

const itemScores = reactive<Record<string, number | null>>({})
const itemNotes = reactive<Record<string, string>>({})
const itemNASelected = reactive<Record<string, boolean>>({})
const counsellingScores = reactive<Record<string, number | null>>({})
const counsellingNotes = reactive<Record<string, string>>({})
const counsellingNASelected = reactive<Record<string, boolean>>({})

function setItemScore(slug: string, value: number | null) {
  const type = getItemType(slug)
  if (type === 'counselling') {
    counsellingScores[slug] = value
    counsellingNASelected[slug] = value === null
  } else {
    itemScores[slug] = value
    itemNASelected[slug] = value === null
  }
}

function handleItemScore(value: number | null) {
  if (currentItem.value?.slug) {
    setItemScore(currentItem.value.slug, value)
  }
}

function handleItemNotes(value: string) {
  if (currentItem.value?.slug) {
    const type = getItemType(currentItem.value.slug)
    if (type === 'counselling') {
      counsellingNotes[currentItem.value.slug] = value
    } else {
      itemNotes[currentItem.value.slug] = value
    }
  }
}

function getCurrentItemNotes(): string {
  if (!currentItem.value?.slug) return ''
  const type = getItemType(currentItem.value.slug)
  return type === 'counselling' 
    ? (counsellingNotes[currentItem.value.slug] ?? '')
    : (itemNotes[currentItem.value.slug] ?? '')
}

function getPreviousScore(itemSlug: string) {
  return sessionStore.getLatestScore(evaluationGroupId.value, itemSlug)
}

function next() {
  if (currentIndex.value < allItems.value.length - 1) {
    currentIndex.value++
  }
}

function prev() {
  if (currentIndex.value > 0) {
    currentIndex.value--
  }
}

function goToItem(index: number) {
  if (index >= 0 && index < allItems.value.length) {
    currentIndex.value = index
  }
}

onKeyStroke('ArrowRight', (e) => { e.preventDefault(); next() })
onKeyStroke('ArrowLeft', (e) => { e.preventDefault(); prev() })

const isValid = computed(() => phase.value !== null && !!evalDateStr.value && roundOfDay.value != null)

// Unsaved-changes guard — covers in-app link clicks, the browser/webview
// back-forward stack (popstate), and the Android hardware back button
// (backButton.client.ts triggers window.history.back(), which router
// guards intercept the same way).
const hasUnsavedChanges = computed(() => {
  if (phase.value !== null) return true
  if (notes.value.trim()) return true
  return allItems.value.some((item) => {
    const score = currentScores.value[item.slug]
    if (score !== null) return true
    const itemNote = item.type === 'counselling' ? counsellingNotes[item.slug] : itemNotes[item.slug]
    return !!itemNote?.trim()
  })
})

const allowNavigation = ref(false)
const showLeaveConfirm = ref(false)
let leaveResolver: ((value: boolean) => void) | null = null

function confirmLeave(): Promise<boolean> {
  showLeaveConfirm.value = true
  return new Promise((resolve) => { leaveResolver = resolve })
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

async function save() {
  if (!isValid.value || !mentee.value || !tool.value || !userStore.currentUser || roundOfDay.value == null) return
  saving.value = true
  try {
    const now = Date.now()
    const evalDate = new Date(evalDateStr.value + 'T12:00:00').getTime()
    const evalGroupId = `${mentee.value.id}::${tool.value.slug}`

    await sessionStore.save({
      _id: '',
      type: 'session',
      evaluationGroupId: evalGroupId,
      mentee: mentee.value,
      evaluator: userStore.currentUser,
      toolSlug: tool.value.slug,
      evalDate,
      roundOfDay: roundOfDay.value,
      facilityId: mentee.value.facilityId ?? '',
      districtId: mentee.value.districtId ?? '',
      itemScores: tool.value.items.map(item => ({
        itemSlug: item.slug,
        menteeScore: (itemScores[item.slug] ?? null) as 1 | 2 | 3 | 4 | 5 | null,
        notes: itemNotes[item.slug]?.trim() || undefined,
      })),
      counsellingScores: counsellingTool.items.map(item => ({
        itemSlug: item.slug,
        menteeScore: (counsellingScores[item.slug] ?? null) as 1 | 2 | 3 | 4 | 5 | null,
        notes: counsellingNotes[item.slug]?.trim() || undefined,
      })),
      phase: phase.value,
      notes: notes.value.trim() || undefined,
      syncStatus: 'pending',
      createdAt: now,
      updatedAt: now,
    })

    toast.add({ title: 'Session saved', color: 'success', icon: 'i-heroicons-check-circle' })
    allowNavigation.value = true
    router.push(`/mentees/${mentee.value.id}`)
  } catch (err) {
    toast.add({ title: 'Save failed', description: String(err), color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    saving.value = false
  }
}

function viewEvaluation() {
  if (!mentee.value || !tool.value) return

  const allItemsData = [
    ...tool.value.items.map(item => ({
      slug: item.slug,
      number: item.number,
      title: item.title,
      category: item.category,
      type: 'tool' as const,
      score: itemScores[item.slug] ?? null,
      notes: (itemNotes[item.slug] || '').trim(),
    })),
    ...counsellingTool.items.map(item => ({
      slug: item.slug,
      number: item.number,
      title: item.title,
      category: 'General Counselling',
      type: 'counselling' as const,
      score: counsellingScores[item.slug] ?? null,
      notes: (counsellingNotes[item.slug] || '').trim(),
    })),
  ]

  const previewData = {
    menteeId: mentee.value.id,
    menteeName: menteeName.value,
    toolSlug: tool.value.slug,
    toolLabel: tool.value.label,
    items: allItemsData,
    phase: phase.value,
    evalDate: evalDateStr.value,
    notes: notes.value,
  }

  sessionStorage.setItem('penplus_preview', JSON.stringify(previewData))
  allowNavigation.value = true
  router.push('/sessions/preview')
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
    <SessionProgressBar
      :items="allItems"
      :current-index="currentIndex"
      :scores="currentScores"
      :current-item-scored="currentItemScored"
      @prev="prev"
      @next="next"
      @goto="goToItem"
    />

    <!-- Previous Sessions Info -->
    <div v-if="totalPreviousSessions > 0" class="bg-orange-50 dark:bg-orange-900/20 border-b border-orange-200 dark:border-orange-800 px-4 py-2">
      <div class="flex items-center gap-2 text-orange-700 dark:text-orange-300">
        <UIcon name="i-heroicons-information-circle" class="w-4 h-4" />
        <p class="text-sm">
          <template v-if="roundsRecordedToday > 0">
            Round {{ roundOfDay }} for this date · Session {{ totalPreviousSessions + 1 }} overall — Previous scores shown below
          </template>
          <template v-else>
            Session {{ totalPreviousSessions + 1 }} — Previous scores shown below
          </template>
        </p>
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

      <SessionRoundSelector v-model="roundOfDay" :options="roundOptions" />

      <!-- Current Item Card -->
      <SessionItemCard
        v-if="currentItem?.slug"
        :item="currentItem"
        :current-score="currentScores[currentItem.slug] ?? null"
        :current-notes="getCurrentItemNotes()"
        :previous-score="getPreviousScore(currentItem.slug)"
        :show-previous="totalPreviousSessions > 0"
        :na-explicitly-selected="isNASelected"
        @score="handleItemScore"
        @notes="handleItemNotes"
      />
         <!-- Session Notes (shown after all items scored) -->
      <section v-if="allItemsScored">
        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
          Session Notes <span class="text-gray-400 font-normal normal-case tracking-normal">(optional)</span>
        </label>
        <UTextarea
          v-model="notes"
          placeholder="Any observations or comments about this session…"
          :rows="3"
          class="w-full"
        />
      </section>

      <!-- Navigation buttons -->
      <div class="flex gap-3">
        <UButton
          variant="soft"
          color="neutral"
          block
          :disabled="currentIndex === 0"
          @click="prev"
        >
          <UIcon name="i-heroicons-chevron-left" class="w-4 h-4 mr-2" />
          Previous
        </UButton>
        
        <UButton
          v-if="currentIndex < allItems.length - 1"
          color="primary"
          block
          :disabled="!currentItemScored"
          @click="next"
        >
          Next
          <UIcon name="i-heroicons-chevron-right" class="w-4 h-4 ml-2" />
        </UButton>
        
        <UButton
          v-else
          color="success"
          block
          :disabled="!allItemsScored"
          @click="viewEvaluation"
        >
          View Evaluation
          <UIcon name="i-heroicons-eye" class="w-4 h-4 ml-2" />
        </UButton>
      </div>


    </div>

    <!-- Unsaved-changes confirmation -->
    <UModal v-model:open="showLeaveConfirm" title="Discard this session?">
      <template #body>
        <p class="text-sm text-gray-700 dark:text-gray-300">
          You have scores or notes entered that haven't been saved. Leaving now will discard them.
        </p>
      </template>
      <template #footer>
        <div class="flex gap-2 justify-end">
          <UButton variant="ghost" color="neutral" @click="resolveLeave(false)">Keep Editing</UButton>
          <UButton color="error" @click="resolveLeave(true)">Discard</UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>