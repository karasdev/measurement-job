<script setup lang="ts">
defineProps<{
  title?: string
}>()
</script>

<template>
  <header class="bg-[#f0f1f4] dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
      <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
        <slot name="title">
          {{ title }}
        </slot>
      </h1>
      <div v-if="$slots.default" class="flex items-center gap-4">
        <slot />
      </div>
    </div>
  </header>
</template>
