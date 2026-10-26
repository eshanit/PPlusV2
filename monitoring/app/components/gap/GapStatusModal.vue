<script setup lang="ts">
import { format } from 'date-fns'
import type { GapStatus, IGapEntry } from '~/interfaces/IGapEntry'
import { GAP_STATUS_LABELS, gapStatus, withGapStatus } from '~/composables/useGapStatus'
import { useGapStore } from '~/stores/gapStore'
import { useUserStore } from '~/stores/userStore'

/**
 * Move a gap to another status: being addressed (with an optional "address
 * by" date), resolved (on a date the mentor picks, today by default), or
 * reopened. Each change is kept in the gap's history.
 */
const props = defineProps<{
  gap: IGapEntry | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [gap: IGapEntry] }>()

const gapStore = useGapStore()
const userStore = useUserStore()
const toast = useToast()

const current = computed(() => (props.gap ? gapStatus(props.gap) : 'open'))

const choices = computed(() => {
  const all: Array<{ to: GapStatus; label: string; hint: string; icon: string }> = [
    { to: 'in_progress', label: GAP_STATUS_LABELS.in_progress, hint: 'Being worked on, optionally with a date to address it by', icon: 'i-heroicons-arrow-path' },
    { to: 'resolved', label: GAP_STATUS_LABELS.resolved, hint: 'The mentee no longer has this gap', icon: 'i-heroicons-check-circle' },
    { to: 'open', label: current.value === 'resolved' ? 'Reopen' : 'Back to open', hint: current.value === 'resolved' ? 'The gap has come back' : 'Not being worked on yet', icon: 'i-heroicons-arrow-uturn-left' },
  ]
  return all.filter(choice => choice.to !== current.value)
})

const to = ref<GapStatus | undefined>(undefined)
const dateStr = ref('')
const targetStr = ref('')
const note = ref('')
const saving = ref(false)

const today = () => format(new Date(), 'yyyy-MM-dd')
const identifiedStr = computed(() => (props.gap ? format(new Date(props.gap.identifiedAt), 'yyyy-MM-dd') : undefined))

watch(open, (isOpen) => {
  if (!isOpen || !props.gap) return
  to.value = choices.value[0]?.to
  dateStr.value = today()
  targetStr.value = props.gap.targetDate ? format(new Date(props.gap.targetDate), 'yyyy-MM-dd') : ''
  note.value = ''
})

const noteLabel = computed(() => ({
  resolved: 'How was it resolved? (optional)',
  in_progress: 'What is the plan? (optional)',
  open: current.value === 'resolved' ? 'Why is it reopened? (optional)' : 'Note (optional)',
})[to.value ?? 'open'])

const dateError = computed(() => {
  if (to.value !== 'resolved') return undefined
  if (!dateStr.value) return 'Choose the date it was resolved.'
  if (dateStr.value > today()) return "The resolved date can't be in the future."
  if (identifiedStr.value && dateStr.value < identifiedStr.value) return "It can't be before the gap was identified."
  return undefined
})

const atNoon = (value: string) => new Date(`${value}T12:00:00`).getTime()

async function save() {
  const gap = props.gap
  const user = userStore.currentUser
  if (!gap || !to.value || !user || dateError.value) return
  saving.value = true
  try {
    const saved = await gapStore.save(withGapStatus(gap, {
      to: to.value,
      date: to.value === 'resolved' ? atNoon(dateStr.value) : undefined,
      targetDate: to.value === 'in_progress' && targetStr.value ? atNoon(targetStr.value) : undefined,
      note: note.value,
      by: user.id,
      byName: `${user.firstname} ${user.lastname}`.trim(),
    }))
    toast.add({ title: `Gap ${to.value === 'open' ? 'reopened' : GAP_STATUS_LABELS[to.value].toLowerCase()}`, color: 'success', icon: 'i-heroicons-check-circle' })
    emit('saved', saved)
    open.value = false
  } catch (error) {
    toast.add({ title: 'Could not update the gap', description: String(error), color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UModal v-model:open="open" title="Update gap" :description="gap?.description">
    <template #body>
      <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Currently <span class="font-semibold text-gray-900 dark:text-white">{{ GAP_STATUS_LABELS[current] }}</span>
        </p>

        <div class="space-y-2" role="radiogroup" aria-label="New status">
          <button
            v-for="choice in choices"
            :key="choice.to"
            type="button"
            role="radio"
            :aria-checked="to === choice.to"
            class="flex min-h-12 w-full items-start gap-3 rounded-lg border-2 px-3 py-2 text-left transition-colors"
            :class="to === choice.to
              ? 'border-primary-600 bg-primary-50 dark:border-primary-400 dark:bg-primary-950/40'
              : 'border-gray-200 bg-white hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900'"
            @click="to = choice.to"
          >
            <UIcon :name="choice.icon" class="mt-0.5 size-5 shrink-0" :class="to === choice.to ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400'" />
            <span class="min-w-0">
              <span class="block text-base font-semibold text-gray-900 dark:text-white">{{ choice.label }}</span>
              <span class="block text-sm text-gray-600 dark:text-gray-300">{{ choice.hint }}</span>
            </span>
          </button>
        </div>

        <div v-if="to === 'resolved'">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Resolved on</label>
          <UInput v-model="dateStr" type="date" :min="identifiedStr" :max="today()" class="w-full" />
          <p v-if="dateError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ dateError }}</p>
          <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">Set an earlier day if it was resolved at a previous visit.</p>
        </div>

        <div v-if="to === 'in_progress'">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Address by <span class="font-normal normal-case tracking-normal text-gray-400">(optional)</span>
          </label>
          <UInput v-model="targetStr" type="date" :min="today()" class="w-full" />
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The gap shows as overdue after this date.</p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ noteLabel }}</label>
          <UTextarea v-model="note" :rows="2" class="w-full" />
        </div>
      </div>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton variant="ghost" color="neutral" @click="open = false">Cancel</UButton>
        <UButton color="primary" :loading="saving" :disabled="!to || !!dateError" @click="save">Save</UButton>
      </div>
    </template>
  </UModal>
</template>
