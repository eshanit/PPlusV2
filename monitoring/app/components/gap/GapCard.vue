<script setup lang="ts">
import type { IGapEntry, GapDomain } from '~/interfaces/IGapEntry'

const props = defineProps<{
  gap: IGapEntry
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

function formatDate(ts: number | undefined): string {
  if (!ts) return '—'
  return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div
    class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border px-4 py-3.5 space-y-2"
    :class="gap.resolvedAt
      ? 'border-gray-100 dark:border-gray-800 opacity-70'
      : 'border-warning-200 dark:border-warning-800'"
  >
    <!-- Top row -->
    <div class="flex items-start justify-between gap-3">
      <p class="text-sm text-gray-900 dark:text-white leading-snug flex-1">{{ gap.description }}</p>
      <UBadge
        v-if="gap.resolvedAt"
        label="Resolved"
        color="success"
        variant="soft"
        size="xs"
        class="shrink-0"
      />
      <UBadge
        v-else
        label="Open"
        color="warning"
        variant="soft"
        size="xs"
        class="shrink-0"
      />
    </div>

    <!-- Domain pills -->
    <div v-if="gap.domains.length" class="flex flex-wrap gap-1">
      <span
        v-for="d in gap.domains"
        :key="d"
        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300"
      >
        {{ domainLabels[d] }}
      </span>
    </div>

    <!-- Meta -->
    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
      <span>Identified {{ formatDate(gap.identifiedAt) }}</span>
      <UButton
        v-if="!gap.resolvedAt"
        size="xs"
        variant="soft"
        color="success"
        icon="i-heroicons-check"
        @click="emit('resolve', gap)"
      >
        Resolve
      </UButton>
      <span v-else class="text-success-600 dark:text-success-400">
        Resolved {{ formatDate(gap.resolvedAt) }}
      </span>
    </div>

    <!-- Resolution note -->
    <p v-if="gap.resolvedAt && gap.resolutionNote" class="text-xs text-gray-500 dark:text-gray-400 italic">
      {{ gap.resolutionNote }}
    </p>
  </div>
</template>
