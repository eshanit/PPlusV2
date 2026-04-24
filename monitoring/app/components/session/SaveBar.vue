<template>
  <div class="sticky top-14 z-30 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between gap-3 shadow-sm">
    <div class="min-w-0">
      <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ menteeName }}</p>
      <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ toolLabel }}</p>
    </div>
    <UButton
      color="primary"
      :loading="saving"
      :disabled="!isValid"
      size="sm"
      @click="$emit('save')"
    >
      Save Session
    </UButton>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  menteeName: string
  toolLabel?: string
  saving: boolean
  isValid: boolean
}>()

defineEmits<{ save: [] }>()
</script>
