<template>
  <section>
    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
      Mentorship Phase <span class="text-red-500">*</span>
    </p>
    <div class="flex gap-2 flex-wrap">
      <button
        v-for="p in PHASES"
        :key="p.value"
        class="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors"
        :class="modelValue === p.value ? p.activeClass : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400'"
        @click="$emit('update:modelValue', p.value)"
      >
        {{ p.label }}
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { MentorshipPhase } from '~/interfaces/ISession'

defineProps<{ modelValue: MentorshipPhase | null }>()
defineEmits<{ 'update:modelValue': [value: MentorshipPhase] }>()

const PHASES: { value: MentorshipPhase; label: string; activeClass: string }[] = [
  { value: 'initial_intensive', label: 'Initial Intensive', activeClass: 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300' },
  { value: 'ongoing', label: 'Ongoing', activeClass: 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
  { value: 'supervision', label: 'Supervision', activeClass: 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' },
]
</script>
