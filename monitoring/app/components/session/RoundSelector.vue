<template>
  <section>
    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
      Round <span class="text-red-500">*</span>
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
      Which round of the day is this? Pick an earlier round if you're typing up paper notes out of order.
    </p>
    <div class="flex gap-2 flex-wrap">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        :disabled="option.disabled"
        class="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors"
        :class="buttonClass(option)"
        @click="!option.disabled && $emit('update:modelValue', option.value)"
      >
        Round {{ option.value }}
        <span v-if="option.disabled" class="opacity-70">(recorded)</span>
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
export interface RoundOption {
  value: number
  disabled: boolean
}

const props = defineProps<{
  modelValue: number | undefined
  options: RoundOption[]
}>()
defineEmits<{ 'update:modelValue': [value: number] }>()

function buttonClass(option: RoundOption): string {
  if (option.disabled) {
    return 'border-gray-100 dark:border-gray-800 text-gray-300 dark:text-gray-600 cursor-not-allowed'
  }
  if (props.modelValue === option.value) {
    return 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
  }
  return 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400'
}
</script>
