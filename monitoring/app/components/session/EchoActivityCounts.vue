<script setup lang="ts">
import { echoAccuracy } from '~/composables/useSessionCalculations'

/**
 * Visit-level echocardiogram counts from the paper tool's two "#" rows.
 * Values are kept as strings so an empty field stays distinct from 0.
 */
const performed = defineModel<string>('performed', { required: true })
const correct = defineModel<string>('correct', { required: true })

const props = defineProps<{
  /** Show missing-value errors even if the fields have not been touched, e.g. after Review is tapped. */
  showErrors?: boolean
}>()

const touched = reactive({ performed: false, correct: false })

function parse(value: string): number | undefined {
  const normalized = value.trim()
  if (!/^\d+$/.test(normalized)) return undefined
  const count = Number(normalized)
  return Number.isSafeInteger(count) ? count : undefined
}

const performedCount = computed(() => parse(performed.value))
const correctCount = computed(() => parse(correct.value))
const accuracy = computed(() => echoAccuracy(performedCount.value, correctCount.value))

const exceeds = computed(() =>
  performedCount.value !== undefined
  && correctCount.value !== undefined
  && correctCount.value > performedCount.value)

function missingError(field: 'performed' | 'correct'): string | undefined {
  const value = field === 'performed' ? performed.value : correct.value
  const count = field === 'performed' ? performedCount.value : correctCount.value
  if (!(touched[field] || props.showErrors)) return undefined
  if (value.trim() === '') return 'Enter a number. Use 0 if none.'
  if (count === undefined) return 'Enter a whole number.'
  return undefined
}

const performedError = computed(() => missingError('performed'))
const correctError = computed(() => exceeds.value
  ? 'Cannot be more than the number performed.'
  : missingError('correct'))

function step(field: 'performed' | 'correct', delta: 1 | -1) {
  const model = field === 'performed' ? performed : correct
  const current = parse(model.value) ?? 0
  let next = Math.max(0, current + delta)
  if (field === 'correct' && performedCount.value !== undefined) {
    next = Math.min(next, performedCount.value)
  }
  model.value = String(next)
  touched[field] = true
}

const fields = computed(() => [
  {
    key: 'performed' as const,
    label: 'Echocardiograms performed under supervision',
    help: 'Done by the mentee, supervised by the mentor, MT or specialist.',
    model: performed,
    count: performedCount.value,
    error: performedError.value,
    canDecrease: (performedCount.value ?? 0) > 0,
    canIncrease: true,
  },
  {
    key: 'correct' as const,
    label: 'With a correct final diagnosis',
    help: 'Of those performed, how many reached the correct final diagnosis.',
    model: correct,
    count: correctCount.value,
    error: correctError.value,
    canDecrease: (correctCount.value ?? 0) > 0,
    canIncrease: performedCount.value === undefined || (correctCount.value ?? 0) < performedCount.value,
  },
])
</script>

<template>
  <section
    id="echo-activity"
    class="scroll-mt-36 space-y-4 rounded-xl border-2 border-sky-300 bg-sky-50 p-4 dark:border-sky-800 dark:bg-sky-950/30"
    aria-labelledby="echo-activity-title"
  >
    <div class="flex items-start gap-3">
      <UIcon name="i-heroicons-clipboard-document-list" class="mt-0.5 size-6 shrink-0 text-sky-700 dark:text-sky-300" />
      <div>
        <h3 id="echo-activity-title" class="text-base font-semibold text-gray-900 dark:text-white">
          Echocardiogram activity · whole visit
        </h3>
        <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">
          Enter one total for this visit, across all rounds. These counts do not affect competency status.
        </p>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div v-for="field in fields" :key="field.key">
        <label :for="`echo-${field.key}`" class="block text-sm font-semibold text-gray-900 dark:text-white">
          {{ field.label }} <span class="text-red-600" aria-hidden="true">*</span>
        </label>
        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">{{ field.help }}</p>
        <div class="flex items-stretch gap-2">
          <UButton
            color="neutral"
            variant="outline"
            size="xl"
            icon="i-heroicons-minus"
            class="w-14 justify-center"
            :disabled="!field.canDecrease"
            :aria-label="`Decrease ${field.label}`"
            @click="step(field.key, -1)"
          />
          <input
            :id="`echo-${field.key}`"
            v-model="field.model.value"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            placeholder="—"
            class="h-14 w-24 rounded-lg border-2 bg-white text-center text-2xl font-bold text-gray-900 outline-none focus:border-primary-500 dark:bg-gray-900 dark:text-white"
            :class="field.error ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"
            :aria-invalid="!!field.error"
            :aria-describedby="field.error ? `echo-${field.key}-error` : undefined"
            aria-required="true"
            @blur="touched[field.key] = true"
          >
          <UButton
            color="neutral"
            variant="outline"
            size="xl"
            icon="i-heroicons-plus"
            class="w-14 justify-center"
            :disabled="!field.canIncrease"
            :aria-label="`Increase ${field.label}`"
            @click="step(field.key, 1)"
          />
        </div>
        <p
          v-if="field.error"
          :id="`echo-${field.key}-error`"
          class="mt-1.5 text-sm font-medium text-red-700 dark:text-red-300"
          role="alert"
        >
          {{ field.error }}
        </p>
      </div>
    </div>

    <p
      v-if="accuracy && !exceeds"
      class="rounded-lg bg-white/80 px-3 py-2 text-base font-semibold text-gray-900 dark:bg-gray-900/60 dark:text-white"
      aria-live="polite"
    >
      Diagnostic accuracy this visit: {{ accuracy.text }}
    </p>
  </section>
</template>
