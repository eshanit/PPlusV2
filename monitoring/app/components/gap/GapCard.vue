<script setup lang="ts">
import type { IGapEntry, GapDomain, SupervisionLevel } from '~/interfaces/IGapEntry'
import { GAP_STATUS_LABELS, gapStatus, isGapOverdue } from '~/composables/useGapStatus'

const props = defineProps<{
  gap: IGapEntry
  /** Hide the status action, e.g. on session pages; gaps are followed up from the journey or Gaps page. */
  readonly?: boolean
}>()

const emit = defineEmits<{
  status: [gap: IGapEntry]
}>()

const status = computed(() => gapStatus(props.gap))
const overdue = computed(() => isGapOverdue(props.gap))
const statusColor = computed(() => ({ open: 'warning', in_progress: 'info', resolved: 'success' } as const)[status.value])
const showHistory = ref(false)

const domainLabels: Record<GapDomain, string> = {
  knowledge: 'Knowledge',
  critical_reasoning: 'Reasoning',
  clinical_skills: 'Clinical Skills',
  communication: 'Communication',
  attitude: 'Attitude',
}

const supervisionLabels: Record<SupervisionLevel, string> = {
  intensive_mentorship: 'Intensive Mentorship',
  ongoing_mentorship: 'Ongoing Mentorship',
  independent_practice: 'Independent Practice',
}

// Same wording as the gap form: gaps logged in a session ask about its round,
// gaps logged afterwards about the visit.
const fromSession = computed(() => !!props.gap.sessionId)
const addressedLabel = computed(() => {
  if (props.gap.coveredInMentorship === true) return fromSession.value ? "Yes — addressed in this session's round" : 'Yes — addressed during the visit'
  if (props.gap.coveredInMentorship === false || props.gap.coveringLater) return 'No — will cover later'
  return 'Not yet decided'
})

const roundNumber = computed(() => props.gap.roundIndex ?? props.gap.sessionRound)

function formatDate(ts: number | undefined): string {
  if (!ts) return '—'
  return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div
    class="space-y-3 rounded-xl border bg-white px-4 py-3.5 shadow-sm dark:bg-gray-900"
    :class="overdue
      ? 'border-red-300 dark:border-red-800'
      : status === 'resolved'
        ? 'border-gray-200 dark:border-gray-800'
        : status === 'in_progress'
          ? 'border-info-200 dark:border-info-800'
          : 'border-warning-200 dark:border-warning-800'"
  >
    <!-- Description and status -->
    <div class="flex items-start justify-between gap-3">
      <p class="flex-1 text-base leading-snug text-gray-900 dark:text-white">{{ gap.description }}</p>
      <span class="flex shrink-0 flex-col items-end gap-1">
        <UBadge :label="GAP_STATUS_LABELS[status]" :color="statusColor" variant="soft" size="md" />
        <UBadge v-if="overdue" label="Overdue" color="error" variant="solid" size="sm" />
      </span>
    </div>

    <!-- Every field from the gap form, labelled the same way -->
    <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[max-content_1fr]">
      <dt class="font-semibold text-gray-600 dark:text-gray-300">Domain(s)</dt>
      <dd class="flex flex-wrap gap-1">
        <span
          v-for="d in gap.domains"
          :key="d"
          class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
        >
          {{ domainLabels[d] }}
        </span>
        <span v-if="!gap.domains.length" class="text-gray-500">—</span>
      </dd>

      <dt class="font-semibold text-gray-600 dark:text-gray-300">{{ fromSession ? "Addressed in this session's round?" : 'Addressed during the visit?' }}</dt>
      <dd class="text-gray-900 dark:text-gray-100">{{ addressedLabel }}</dd>

      <template v-if="gap.timeline">
        <dt class="font-semibold text-gray-600 dark:text-gray-300">Target timeline</dt>
        <dd class="text-gray-900 dark:text-gray-100">{{ gap.timeline }}</dd>
      </template>

      <dt class="font-semibold text-gray-600 dark:text-gray-300">Supervision recommendation</dt>
      <dd class="text-gray-900 dark:text-gray-100">
        {{ gap.supervisionLevel ? supervisionLabels[gap.supervisionLevel] : 'Not specified' }}
      </dd>

      <dt class="font-semibold text-gray-600 dark:text-gray-300">Identified</dt>
      <dd class="text-gray-900 dark:text-gray-100">
        {{ formatDate(gap.identifiedAt) }}<template v-if="roundNumber != null"> · Round {{ roundNumber }}</template>
      </dd>

      <template v-if="status === 'in_progress' && gap.targetDate">
        <dt class="font-semibold" :class="overdue ? 'text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'">Address by</dt>
        <dd :class="overdue ? 'font-semibold text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'">{{ formatDate(gap.targetDate) }}</dd>
      </template>

      <template v-if="gap.resolvedAt">
        <dt class="font-semibold text-success-700 dark:text-success-400">Resolved</dt>
        <dd class="text-gray-900 dark:text-gray-100">
          {{ formatDate(gap.resolvedAt) }}
          <span v-if="gap.resolutionNote" class="mt-0.5 block text-gray-600 dark:text-gray-300">{{ gap.resolutionNote }}</span>
        </dd>
      </template>
    </dl>

    <div v-if="gap.statusHistory?.length">
      <button
        type="button"
        class="flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
        :aria-expanded="showHistory"
        @click="showHistory = !showHistory"
      >
        <UIcon name="i-heroicons-chevron-right" class="size-4 transition-transform" :class="showHistory ? 'rotate-90' : ''" />
        History ({{ gap.statusHistory.length }})
      </button>
      <ol v-if="showHistory" class="mt-2 space-y-1.5 border-l-2 border-gray-200 pl-3 dark:border-gray-700">
        <li v-for="(change, index) in gap.statusHistory" :key="index" class="text-sm">
          <span class="font-semibold text-gray-900 dark:text-white">{{ GAP_STATUS_LABELS[change.status] }}</span>
          <span class="text-gray-600 dark:text-gray-300">
            · {{ formatDate(change.date) }}<template v-if="change.byName"> by {{ change.byName }}</template>
            <template v-if="change.targetDate"> · address by {{ formatDate(change.targetDate) }}</template>
          </span>
          <span v-if="change.note" class="block text-gray-600 dark:text-gray-300">{{ change.note }}</span>
        </li>
      </ol>
    </div>

    <div v-if="!readonly" class="flex justify-end">
      <UButton
        size="md"
        variant="soft"
        :color="status === 'resolved' ? 'neutral' : 'primary'"
        icon="i-heroicons-arrows-right-left"
        @click="emit('status', gap)"
      >
        {{ status === 'resolved' ? 'Reopen…' : 'Update status' }}
      </UButton>
    </div>
  </div>
</template>
