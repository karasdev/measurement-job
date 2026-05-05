<script setup lang="ts">
defineProps<{
  title?: string
}>()
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <AppHeader :title="title">
      <template v-if="$slots.title" #title>
        <slot name="title" />
      </template>
      <slot name="headerActions" />
    </AppHeader>
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <slot />
    </main>
  </div>
</template>
