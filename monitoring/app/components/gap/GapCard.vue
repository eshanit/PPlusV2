<script setup lang="ts">
import type { IGapEntry, GapDomain, SupervisionLevel } from '~/interfaces/IGapEntry'

const props = defineProps<{
  gap: IGapEntry
  /** Hide the Resolve action, e.g. on session pages; gaps are resolved from the journey. */
  readonly?: boolean
}>()

const emit = defineEmits<{
  resolve: [gap: IGapEntry]
}>()

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

// Same wording as the gap form's "Addressed in this session's round?" options.
const addressedLabel = computed(() => {
  if (props.gap.coveredInMentorship === true) return "Yes — addressed in this session's round"
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
    :class="gap.resolvedAt
      ? 'border-gray-200 dark:border-gray-800'
      : 'border-warning-200 dark:border-warning-800'"
  >
    <!-- Description and status -->
    <div class="flex items-start justify-between gap-3">
      <p class="flex-1 text-base leading-snug text-gray-900 dark:text-white">{{ gap.description }}</p>
      <UBadge
        :label="gap.resolvedAt ? 'Resolved' : 'Open'"
        :color="gap.resolvedAt ? 'success' : 'warning'"
        variant="soft"
        size="md"
        class="shrink-0"
      />
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

      <dt class="font-semibold text-gray-600 dark:text-gray-300">Addressed in this session's round?</dt>
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

      <template v-if="gap.resolvedAt">
        <dt class="font-semibold text-success-700 dark:text-success-400">Resolved</dt>
        <dd class="text-gray-900 dark:text-gray-100">
          {{ formatDate(gap.resolvedAt) }}
          <span v-if="gap.resolutionNote" class="mt-0.5 block text-gray-600 dark:text-gray-300">{{ gap.resolutionNote }}</span>
        </dd>
      </template>
    </dl>

    <div v-if="!gap.resolvedAt && !readonly" class="flex justify-end">
      <UButton
        size="md"
        variant="soft"
        color="success"
        icon="i-heroicons-check"
        @click="emit('resolve', gap)"
      >
        Resolve
      </UButton>
    </div>
  </div>
</template>
