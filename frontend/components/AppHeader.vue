<script setup lang="ts">
defineProps<{
  title?: string
}>()
</script>

<template>
  <header class="border-b border-gray-200 bg-white/95 shadow-sm backdrop-blur dark:border-gray-800 dark:bg-gray-950/95">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
      <h1 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
        <slot name="title">
          {{ title }}
        </slot>
      </h1>
      <div v-if="$slots.default" class="flex items-center gap-2 sm:gap-3">
        <slot />
      </div>
    </div>
  </header>
</template>
