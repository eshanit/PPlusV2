<script setup lang="ts">
import type { IEvalItem } from '~/interfaces/IEvalItem'

interface Props {
  item: IEvalItem & { type: 'tool' | 'counselling' }
  currentScore: number | null
  currentNotes?: string
  previousScore: { score: number | null; date: number; sessionNumber: number } | null
  showPrevious?: boolean
  naExplicitlySelected?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showPrevious: true,
  currentNotes: '',
  naExplicitlySelected: false,
})

const emit = defineEmits<{
  score: [value: number | null]
  notes: [value: string]
}>()

function selectScore(value: number | null) {
  emit('score', value)
}

function updateNotes(value: string) {
  emit('notes', value)
}

function formatDate(ts: number): string {
  return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

function getImprovementColor(current: number | null, previous: number | null): string {
  if (!current || !previous) return ''
  if (current > previous) return 'bg-green-500'
  if (current < previous) return 'bg-red-500'
  return 'bg-gray-400'
}

function getImprovementIcon(current: number | null, previous: number | null): string {
  if (!current || !previous) return 'i-heroicons-minus'
  if (current > previous) return 'i-heroicons-arrow-up'
  if (current < previous) return 'i-heroicons-arrow-down'
  return 'i-heroicons-minus'
}

function getProgressStatus(current: number | null, previous: number | null): string {
  if (!current || !previous) return 'No Change'
  if (current > previous) return 'Improved'
  if (current < previous) return 'Regressed'
  return 'No Change'
}

function getProgressColor(current: number | null, previous: number | null): string {
  if (!current || !previous) return 'bg-gray-400'
  if (current > previous) return 'bg-green-500'
  if (current < previous) return 'bg-red-500'
  return 'bg-gray-400'
}

function getProgressIconName(current: number | null, previous: number | null): string {
  if (!current || !previous) return 'i-heroicons-minus'
  if (current > previous) return 'i-heroicons-arrow-trending-up'
  if (current < previous) return 'i-heroicons-arrow-trending-down'
  return 'i-heroicons-minus'
}

function getProgressTextColor(current: number | null, previous: number | null): string {
  if (!current || !previous) return 'text-gray-500 dark:text-gray-400'
  if (current > previous) return 'text-green-600 dark:text-green-400'
  if (current < previous) return 'text-red-600 dark:text-red-400'
  return 'text-gray-500 dark:text-gray-400'
}
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
    <div class="flex items-start gap-3">
      <!-- Item number badge -->
      <div
        class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-white text-sm"
        :class="item.type === 'counselling' ? 'bg-orange-500' : 'bg-primary-500'"
      >
        {{ item.number }}
      </div>
      
      <div class="flex-1">
        <!-- Category -->
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
          {{ item.type === 'counselling' ? 'General Counselling' : item.category }}
        </p>
        
        <!-- Title -->
        <h3 class="font-medium text-gray-900 dark:text-white mb-4">
          {{ item.title }}
        </h3>
        
        <!-- Previous Score -->
        <div v-if="showPrevious && previousScore" class="mb-4">
          <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
            Score Comparison
          </p>
          <div class="grid grid-cols-3 gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <!-- Previous Score -->
            <div class="text-center">
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Previous</p>
              <p class="text-xl font-bold text-gray-700 dark:text-gray-200">
                {{ previousScore.score }}
              </p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Session {{ previousScore.sessionNumber }}
              </p>
              <p class="text-xs text-gray-400 dark:text-gray-500">
                {{ formatDate(previousScore.date) }}
              </p>
            </div>
            <!-- Current Score -->
            <div class="text-center">
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Current</p>
              <p class="text-xl font-bold"
                :class="currentScore
                  ? 'text-gray-700 dark:text-gray-200'
                  : 'text-gray-400 dark:text-gray-500'">
                {{ currentScore ?? '—' }}
              </p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Session {{ previousScore.sessionNumber + 1 }}
              </p>
            </div>
            <!-- Progress -->
            <div class="text-center">
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Progress</p>
              <div class="flex items-center justify-center h-8">
                <UIcon
                  v-if="currentScore !== null"
                  :name="getProgressIconName(currentScore, previousScore.score)"
                  class="w-6 h-6"
                  :class="getProgressColor(currentScore, previousScore.score)"
                />
                <UIcon
                  v-else
                  name="i-heroicons-minus"
                  class="w-6 h-6 text-gray-400"
                />
              </div>
              <p class="text-xs font-medium mt-1" :class="getProgressTextColor(currentScore, previousScore.score)">
                {{ getProgressStatus(currentScore, previousScore.score) }}
              </p>
            </div>
          </div>
        </div>
        
        <div v-else-if="showPrevious && !previousScore" class="text-xs text-gray-500 mb-4 p-2">
          No previous score for this item
        </div>
        
        <!-- Score buttons -->
        <div class="grid grid-cols-3 gap-2 mb-4">
          <button
            v-for="score in [1, 2, 3, 4, 5, null]"
            :key="score ?? 'na'"
            class="p-3 rounded-lg text-center text-sm font-medium transition-all relative"
            :class="currentScore === score
              ? score === 1 ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 border-2 border-red-500'
              : score === 2 ? 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300 border-2 border-orange-500'
              : score === 3 ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border-2 border-blue-500'
              : score === 4 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border-2 border-green-500'
              : score === 5 ? 'bg-teal-100 dark:bg-teal-900 text-teal-700 dark:text-teal-300 border-2 border-teal-500'
              : 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-2 border-gray-200 dark:border-gray-700'
              : 'bg-gray-50 dark:bg-gray-800 text-gray-400 border-2 border-gray-100 dark:border-gray-700 hover:border-gray-300'"
            @click="selectScore(score)"
          >
            {{ score ?? 'N/A' }}
            
            <!-- Improvement indicator -->
            <span
              v-if="currentScore === score && previousScore && score !== null"
              class="absolute -top-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center"
              :class="getImprovementColor(score, previousScore.score)"
            >
              <UIcon
                :name="getImprovementIcon(score, previousScore.score)"
                class="w-2 h-2 text-white"
              />
            </span>
          </button>
        </div>
        
        <p class="text-xs text-gray-500 mb-3 text-center">
          1=Absent, 2=Basic, 3=Satisfactory, 4=Good, 5=Excellent, N/A=Not evaluated
        </p>

        <!-- N/A Warning -->
        <div
          v-if="naExplicitlySelected && !currentNotes"
          class="mb-4 p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg"
        >
          <div class="flex items-start gap-2">
            <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" />
            <div>
              <p class="text-sm font-medium text-orange-700 dark:text-orange-300">
                Please provide a reason for not evaluating this item
              </p>
              <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                Notes are required when selecting N/A to document why this item was not evaluated.
              </p>
            </div>
          </div>
        </div>

        <!-- Item Notes -->
        <div>
          <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1">
            Notes for this item
          </label>
          <UTextarea
            :model-value="currentNotes"
            :placeholder="naExplicitlySelected ? 'Required: Explain why this item was not evaluated...' : 'Optional observations for this specific item...'"
            :rows="2"
            @update:model-value="updateNotes"
            class="w-full"
          />
        </div>
      </div>
    </div>
  </div>
</template>