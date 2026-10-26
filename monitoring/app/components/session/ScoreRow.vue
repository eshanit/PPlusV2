<script setup lang="ts">
import { format } from 'date-fns'
import type { IEvalItem } from '~/interfaces/IEvalItem'
import type { INotAssessable, NotAssessableReason } from '~/interfaces/IItemScore'
import {
  MENTOR_LABELS,
  NA_LABEL,
  NOT_ASSESSABLE_REASONS,
  SCORE_LABELS,
  notAssessableLabel,
  scoreBackground,
  scoreTextColor,
} from '~/data/evaluationItemData'
import { getScoreChangeDisplay } from '~/composables/useSessionCalculations'

type ScoreValue = 1 | 2 | 3 | 4 | 5 | null

interface PreviousAssessment {
  score: ScoreValue
  evalDate: number
  sessionNumber: number
  roundNumber: number
  notAssessable?: INotAssessable
}

const props = defineProps<{
  item: IEvalItem
  score: ScoreValue
  isScored: boolean
  previousAssessment?: PreviousAssessment
  counselling?: boolean
  note?: string
  /** Supporting information shown under the title, e.g. echo accuracy next to E8. */
  context?: string
  /** Offer "Can't be assessed here". Tool items only, not counselling. */
  allowNotAssessable?: boolean
  /** Set when this round marks the item not assessable. */
  notAssessable?: INotAssessable
}>()

const emit = defineEmits<{
  score: [value: ScoreValue]
  note: [value: string]
  notAssessable: [value: INotAssessable | undefined]
}>()

// Reason picker. The mentor picks one reason; Other needs a note.
const showReasonPicker = ref(false)
const draftReason = ref<NotAssessableReason | undefined>(undefined)
const draftReasonNote = ref('')

function openReasonPicker() {
  draftReason.value = props.notAssessable?.reason
  draftReasonNote.value = props.notAssessable?.note ?? ''
  showReasonPicker.value = true
}

const reasonNoteRequired = computed(() => draftReason.value === 'other' && !draftReasonNote.value.trim())

function saveReason() {
  if (!draftReason.value || reasonNoteRequired.value) return
  emit('notAssessable', { reason: draftReason.value, note: draftReasonNote.value.trim() || undefined })
  showReasonPicker.value = false
}

const scoreOptions: { value: ScoreValue; label: string }[] = [
  { value: 1, label: '1' },
  { value: 2, label: '2' },
  { value: 3, label: '3' },
  { value: 4, label: '4' },
  { value: 5, label: '5' },
  { value: null, label: 'N/A' },
]

function isSelected(value: ScoreValue): boolean {
  return props.isScored && !props.notAssessable && props.score === value
}

function selectedStyle(value: ScoreValue) {
  if (!isSelected(value)) return undefined
  return { backgroundColor: scoreBackground(value), color: scoreTextColor(value) }
}

const selectedScoreLabel = computed(() => {
  if (!props.isScored || props.notAssessable) return undefined
  return props.score === null ? NA_LABEL : SCORE_LABELS[props.score]
})

// The paper tool defines each score for both the mentee and the mentor.
// This is the meaning of the one recorded score, not a second score.
const selectedMentorLabel = computed(() => {
  if (!props.isScored || props.score === null) return undefined
  return MENTOR_LABELS[props.score]
})

const scoreChangeDisplay = computed(() => props.isScored && !props.notAssessable
  ? getScoreChangeDisplay(props.score, props.previousAssessment?.score)
  : undefined)
</script>

<template>
  <article
    class="rounded-lg border p-3 md:p-4"
    :class="item.isAdvanced
      ? 'border-gray-400 border-l-4 bg-gray-200 dark:border-gray-600 dark:bg-gray-800'
      : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'"
  >
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:gap-4">
      <div class="flex min-w-0 flex-1 items-start gap-2">
        <span
          class="inline-flex min-w-10 shrink-0 items-center justify-center rounded px-2 py-1 text-sm font-bold"
          :class="item.isAdvanced
            ? 'bg-gray-700 text-white dark:bg-gray-600'
            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'"
        >
          {{ item.number }}
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-base leading-snug text-gray-900 dark:text-gray-100">{{ item.title }}</p>
          <p
            v-if="context"
            class="mt-1.5 flex items-start gap-1.5 rounded-md bg-sky-50 px-2 py-1 text-sm text-sky-900 dark:bg-sky-950/40 dark:text-sky-100"
          >
            <UIcon name="i-heroicons-information-circle" class="mt-0.5 size-4 shrink-0" />
            <span>{{ context }}</span>
          </p>
          <div v-if="item.isAdvanced || counselling" class="mt-1 flex flex-wrap gap-1.5">
            <span
              v-if="item.isAdvanced"
              class="rounded-full bg-gray-700 px-2 py-0.5 text-xs font-semibold uppercase text-white dark:bg-gray-600"
              title="Not required to be achieved to be considered a competent provider"
            >
              Advanced
            </span>
            <span
              v-if="counselling"
              class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
              Counselling
            </span>
          </div>
        </div>
      </div>

      <div
        class="grid shrink-0 grid-cols-6 gap-2 md:w-[22rem]"
        role="group"
        :aria-label="`Score for ${item.number}`"
      >
        <button
          v-for="option in scoreOptions"
          :key="option.label"
          type="button"
          class="min-h-12 rounded-lg border-2 px-1 text-base font-bold transition-colors"
          :class="isSelected(option.value)
            ? 'border-gray-900 shadow-sm ring-2 ring-gray-900/25 dark:border-white dark:ring-white/30'
            : 'border-gray-300 bg-white text-gray-800 hover:border-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100'"
          :style="selectedStyle(option.value)"
          :aria-pressed="isSelected(option.value)"
          :title="option.value === null ? NA_LABEL : `Score ${option.value}`"
          @click="emit('score', option.value)"
        >
          {{ option.label }}
        </button>
      </div>
    </div>

    <div v-if="selectedScoreLabel" class="mt-3 flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
      <div
        class="min-w-[16rem] flex-1 overflow-hidden rounded-lg border-2"
        :style="{ borderColor: scoreBackground(score) }"
        role="status"
        :aria-label="`Score ${score ?? 'N/A'}`"
      >
        <p
          class="px-3 py-1.5 text-base font-bold"
          :style="{ backgroundColor: scoreBackground(score), color: scoreTextColor(score) }"
        >
          {{ score === null ? 'N/A' : `Score ${score}` }}
        </p>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 bg-white px-3 py-2 text-base dark:bg-gray-900">
          <dt class="flex items-center font-semibold text-gray-600 dark:text-gray-300">Mentee<UIcon name="i-heroicons-arrow-right" class="mx-1 inline-block size-4 shrink-0 align-[-0.125em]" aria-hidden="true" /></dt>
          <dd class="text-gray-900 dark:text-gray-100">{{ selectedScoreLabel }}</dd>
          <template v-if="selectedMentorLabel">
            <dt class="flex items-center font-semibold text-gray-600 dark:text-gray-300">Mentor<UIcon name="i-heroicons-arrow-right" class="mx-1 inline-block size-4 shrink-0 align-[-0.125em]" aria-hidden="true" /></dt>
            <dd class="text-gray-900 dark:text-gray-100">{{ selectedMentorLabel }}</dd>
          </template>
        </dl>
      </div>
      <span
        v-if="scoreChangeDisplay"
        class="inline-flex items-center gap-1 text-sm font-semibold"
        :class="scoreChangeDisplay.class"
        role="status"
        :aria-label="scoreChangeDisplay.label"
      >
        <UIcon :name="scoreChangeDisplay.icon" class="h-4 w-4" />
        {{ scoreChangeDisplay.label }}
      </span>
    </div>

    <!-- Not assessable at this facility: set with a reason, lifted by scoring -->
    <div
      v-if="notAssessable"
      class="mt-3 flex flex-wrap items-start justify-between gap-2 rounded-lg border-2 border-slate-400 bg-slate-100 px-3 py-2 dark:border-slate-500 dark:bg-slate-800"
      role="status"
    >
      <div class="min-w-0">
        <p class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
          <UIcon name="i-heroicons-no-symbol" class="size-5 shrink-0" />
          Not assessable · {{ notAssessableLabel(notAssessable.reason) }}
        </p>
        <p v-if="notAssessable.note" class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ notAssessable.note }}</p>
        <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
          Doesn't count towards competency until it is scored. Tap a score to replace it.
        </p>
      </div>
      <div class="flex shrink-0 gap-2">
        <UButton size="sm" color="neutral" variant="outline" @click="openReasonPicker">Change reason</UButton>
        <UButton size="sm" color="neutral" variant="ghost" @click="emit('notAssessable', undefined)">Remove</UButton>
      </div>
    </div>
    <div v-else-if="allowNotAssessable" class="mt-2 flex justify-end">
      <UButton size="sm" color="neutral" variant="link" icon="i-heroicons-no-symbol" class="px-0" @click="openReasonPicker">
        Can't be assessed here…
      </UButton>
    </div>

    <div
      v-if="previousAssessment"
      class="mt-2 rounded-md bg-gray-50 px-2.5 py-2 text-sm text-gray-600 dark:bg-gray-900/70 dark:text-gray-300"
    >
      <span class="font-semibold">Previous score:</span>
      {{ previousAssessment.notAssessable
        ? `Not assessable (${notAssessableLabel(previousAssessment.notAssessable.reason)})`
        : previousAssessment.score === null ? 'N/A' : previousAssessment.score }}
      <template v-if="previousAssessment.score !== null">
        <span class="mt-0.5 block"><span class="font-semibold">Mentee</span><UIcon name="i-heroicons-arrow-right" class="mx-1 inline-block size-4 shrink-0 align-[-0.125em]" aria-hidden="true" />{{ SCORE_LABELS[previousAssessment.score] }}</span>
        <span class="block"><span class="font-semibold">Mentor</span><UIcon name="i-heroicons-arrow-right" class="mx-1 inline-block size-4 shrink-0 align-[-0.125em]" aria-hidden="true" />{{ MENTOR_LABELS[previousAssessment.score] }}</span>
      </template>
      <span class="mt-0.5 block text-gray-500 dark:text-gray-400">
        {{ format(new Date(previousAssessment.evalDate), 'd MMM yyyy') }}
        · Session {{ previousAssessment.sessionNumber }}
        · Round {{ previousAssessment.roundNumber }}
      </span>
    </div>

    <!-- Notes are for scored items only. N/A needs no explanation (client request). -->
    <UTextarea
      v-if="isScored && score !== null && !notAssessable"
      :model-value="note ?? ''"
      class="mt-2 w-full"
      :rows="2"
      size="lg"
      placeholder="Notes (optional)"
      :aria-label="`Notes for ${item.number}`"
      @update:model-value="emit('note', String($event))"
    />

    <UModal
      v-model:open="showReasonPicker"
      :title="`${item.number} can't be assessed here`"
      description="The item won't count towards this mentee's competency until it is scored. Choose why."
    >
      <template #body>
        <div class="space-y-2" role="radiogroup" :aria-label="`Why ${item.number} can't be assessed`">
          <button
            v-for="reason in NOT_ASSESSABLE_REASONS"
            :key="reason.value"
            type="button"
            role="radio"
            :aria-checked="draftReason === reason.value"
            class="flex min-h-12 w-full items-start gap-3 rounded-lg border-2 px-3 py-2 text-left transition-colors"
            :class="draftReason === reason.value
              ? 'border-primary-600 bg-primary-50 dark:border-primary-400 dark:bg-primary-950/40'
              : 'border-gray-200 bg-white hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900'"
            @click="draftReason = reason.value"
          >
            <UIcon
              :name="draftReason === reason.value ? 'i-heroicons-check-circle-solid' : 'i-heroicons-stop-circle'"
              class="mt-0.5 size-5 shrink-0"
              :class="draftReason === reason.value ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400'"
            />
            <span class="min-w-0">
              <span class="block text-base font-semibold text-gray-900 dark:text-white">{{ reason.label }}</span>
              <span class="block text-sm text-gray-600 dark:text-gray-300">{{ reason.hint }}</span>
            </span>
          </button>
          <UTextarea
            v-model="draftReasonNote"
            class="w-full pt-1"
            :rows="2"
            size="lg"
            :placeholder="draftReason === 'other' ? 'Note (required for Other)' : 'Note (optional), e.g. no potassium test at the lab'"
            :aria-label="`Note on why ${item.number} can't be assessed`"
          />
          <p v-if="reasonNoteRequired" class="text-sm text-amber-700 dark:text-amber-400">
            Add a note saying why.
          </p>
        </div>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" @click="showReasonPicker = false">Cancel</UButton>
          <UButton color="primary" :disabled="!draftReason || reasonNoteRequired" @click="saveReason">
            Mark not assessable
          </UButton>
        </div>
      </template>
    </UModal>
  </article>
</template>
