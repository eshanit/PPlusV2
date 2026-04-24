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

const itemScores = reactive<Record<string, number | null>>({})
const counsellingScores = reactive<Record<string, number | null>>({})

interface ItemGroup { category: string; items: IEvalItem[] }

const itemGroups = computed((): ItemGroup[] => {
  if (!tool.value) return []
  const map = new Map<string, IEvalItem[]>()
  for (const item of tool.value.items) {
    const arr = map.get(item.category) ?? []
    arr.push(item)
    map.set(item.category, arr)
  }
  return Array.from(map.entries()).map(([category, items]) => ({ category, items }))
})

const counsellingItems = counsellingTool.items

const isValid = computed(() => phase.value !== null && !!evalDateStr.value)

function setItemScore(slug: string, value: number | null) { itemScores[slug] = value }
function setCounsellingScore(slug: string, value: number | null) { counsellingScores[slug] = value }

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
      facilityId: '',
      districtId: '',
      itemScores: tool.value.items.map(item => ({
        itemSlug: item.slug,
        menteeScore: (itemScores[item.slug] ?? null) as 1 | 2 | 3 | 4 | 5 | null,
      })),
      counsellingScores: counsellingItems.map(item => ({
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

    <div class="space-y-6 py-5">

      <SessionPhaseSelector v-model="phase" />

      <section>
        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
          Evaluation Date <span class="text-red-500">*</span>
        </label>
        <UInput type="date" v-model="evalDateStr" class="max-w-xs" />
      </section>

      <section v-if="tool">
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
          {{ tool.label }}
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">
          Score each item: 1 (absent) → 5 (excellent). Tap N/A if not evaluated.
        </p>
        <div v-for="group in itemGroups" :key="group.category" class="mb-4">
          <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest py-2 border-b border-gray-100 dark:border-gray-800 mb-1">
            {{ group.category }}
          </p>
          <SessionEvalItemRow
            v-for="item in group.items"
            :key="item.slug"
            :item="item"
            :model-value="itemScores[item.slug] ?? null"
            @update:model-value="setItemScore(item.slug, $event)"
          />
        </div>
      </section>

      <section>
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
          General Counselling (DC1–DC9)
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">
          Always assessed — included in every session.
        </p>
        <SessionEvalItemRow
          v-for="item in counsellingItems"
          :key="item.slug"
          :item="item"
          :model-value="counsellingScores[item.slug] ?? null"
          @update:model-value="setCounsellingScore(item.slug, $event)"
        />
      </section>

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

      <div class="h-4" />
    </div>
  </div>
</template>


