<script setup lang="ts">
import type { GapDomain, SupervisionLevel } from '~/interfaces/IGapEntry'

const props = defineProps<{
  open: boolean
  evaluationGroupId: string
  menteeId: string
  evaluatorId: string
  toolSlug: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  saved: []
}>()

import { useGapStore } from '~/stores/gapStore'
const gapStore = useGapStore()
const toast = useToast()

const saving = ref(false)

const domainOptions: { value: GapDomain; label: string }[] = [
  { value: 'knowledge', label: 'Knowledge' },
  { value: 'critical_reasoning', label: 'Critical Reasoning' },
  { value: 'clinical_skills', label: 'Clinical Skills' },
  { value: 'communication', label: 'Communication' },
  { value: 'attitude', label: 'Attitude' },
]

const supervisionOptions: { label: string; value: SupervisionLevel }[] = [
  { label: 'Intensive Mentorship', value: 'intensive_mentorship' },
  { label: 'Ongoing Mentorship', value: 'ongoing_mentorship' },
  { label: 'Independent Practice', value: 'independent_practice' },
]

const coveredOptions = [
  { label: 'Yes — addressed in this session', value: 'yes' },
  { label: 'No — will cover later', value: 'no' },
  { label: 'Not yet decided', value: 'undecided' },
]

const description = ref('')
const selectedDomains = ref<GapDomain[]>([])
const coveredChoice = ref<'yes' | 'no' | 'undecided'>('undecided')
const timeline = ref('')
const supervisionLevel = ref<SupervisionLevel | undefined>(undefined)

const showTimeline = computed(() => coveredChoice.value === 'no')
const isValid = computed(() => description.value.trim().length > 0 && selectedDomains.value.length > 0)

function toggleDomain(d: GapDomain) {
  const idx = selectedDomains.value.indexOf(d)
  if (idx >= 0) {
    selectedDomains.value.splice(idx, 1)
  } else {
    selectedDomains.value.push(d)
  }
}

function reset() {
  description.value = ''
  selectedDomains.value = []
  coveredChoice.value = 'undecided'
  timeline.value = ''
  supervisionLevel.value = undefined
}

async function save() {
  if (!isValid.value) return
  saving.value = true
  try {
    const now = Date.now()
    await gapStore.save({
      _id: '',
      type: 'gap',
      evaluationGroupId: props.evaluationGroupId,
      menteeId: props.menteeId,
      evaluatorId: props.evaluatorId,
      toolSlug: props.toolSlug,
      identifiedAt: now,
      description: description.value.trim(),
      domains: [...selectedDomains.value],
      coveredInMentorship: coveredChoice.value === 'yes' ? true : coveredChoice.value === 'no' ? false : null,
      coveringLater: coveredChoice.value === 'no',
      timeline: showTimeline.value && timeline.value.trim() ? timeline.value.trim() : undefined,
      supervisionLevel: supervisionLevel.value || undefined,
      syncStatus: 'pending',
      createdAt: now,
      updatedAt: now,
    })
    toast.add({ title: 'Gap logged', color: 'success', icon: 'i-heroicons-check-circle' })
    reset()
    emit('update:open', false)
    emit('saved')
  } catch (err) {
    toast.add({ title: 'Save failed', description: String(err), color: 'error', icon: 'i-heroicons-x-circle' })
  } finally {
    saving.value = false
  }
}

function close() {
  reset()
  emit('update:open', false)
}
</script>

<template>
  <UDrawer :open="open" title="Log a Gap" @update:open="emit('update:open', $event)">
    <template #body>
      <div class="space-y-5 px-4 pb-4">
        <!-- Description -->
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
            Description <span class="text-error-500">*</span>
          </label>
          <UTextarea
            v-model="description"
            placeholder="Describe the specific gap observed…"
            :rows="3"
            class="w-full"
          />
        </div>

        <!-- Domains -->
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
            Domain(s) <span class="text-error-500">*</span>
          </label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="opt in domainOptions"
              :key="opt.value"
              type="button"
              class="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
              :class="selectedDomains.includes(opt.value)
                ? 'bg-primary-500 border-primary-500 text-white'
                : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-primary-400'"
              @click="toggleDomain(opt.value)"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <!-- Covered in mentorship -->
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
            Addressed in this session?
          </label>
          <div class="flex flex-col gap-1.5">
            <label
              v-for="opt in coveredOptions"
              :key="opt.value"
              class="flex items-center gap-2 cursor-pointer"
            >
              <input
                type="radio"
                :value="opt.value"
                v-model="coveredChoice"
                class="accent-primary-500"
              />
              <span class="text-sm text-gray-800 dark:text-gray-200">{{ opt.label }}</span>
            </label>
          </div>
        </div>

        <!-- Timeline (only when covering later) -->
        <div v-if="showTimeline">
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
            Target Timeline <span class="text-gray-400 font-normal normal-case tracking-normal">(optional)</span>
          </label>
          <UInput v-model="timeline" placeholder="e.g. next session, within 2 weeks" class="w-full" />
        </div>

        <!-- Supervision recommendation -->
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
            Supervision Recommendation <span class="text-gray-400 font-normal normal-case tracking-normal">(optional)</span>
          </label>
          <USelect
            v-model="supervisionLevel"
            :items="supervisionOptions"
            placeholder="Not specified"
            class="w-full"
          />
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex gap-3 px-4 pb-6 pt-2">
        <UButton variant="outline" color="neutral" class="flex-1" @click="close">
          Cancel
        </UButton>
        <UButton
          color="primary"
          class="flex-1"
          :loading="saving"
          :disabled="!isValid"
          @click="save"
        >
          Log Gap
        </UButton>
      </div>
    </template>
  </UDrawer>
</template>
