<template>
  <div
    class="py-3 border-b border-gray-50 dark:border-gray-800/50"
    :class="{ 'opacity-60': item.isAdvanced }"
  >
    <div class="flex items-start gap-2 mb-2">
      <span class="font-mono text-xs font-bold text-gray-400 dark:text-gray-500 shrink-0 mt-0.5 w-8">
        {{ item.number }}
      </span>
      <span class="text-sm text-gray-800 dark:text-gray-200 leading-snug">{{ item.title }}</span>
      <UBadge
        v-if="item.isAdvanced"
        label="Adv"
        color="neutral"
        variant="soft"
        size="xs"
        class="shrink-0 mt-0.5"
      />
    </div>
    <div class="pl-8">
      <SessionScoreButtons
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { IEvalItem } from '~/interfaces/IEvalItem'

defineProps<{
  item: IEvalItem
  modelValue: number | null
}>()

defineEmits<{ 'update:modelValue': [value: number | null] }>()
</script>
