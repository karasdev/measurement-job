<script setup lang="ts">
defineProps<{
  title?: string
}>()
</script>

<template>
  <div class="min-h-screen bg-[#f5f6f8] dark:bg-gray-900">
    <AppHeader :title="title">
      <template v-if="$slots.title" #title>
        <slot name="title" />
      </template>
      <slot name="headerActions" />
    </AppHeader>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <slot />
    </main>
  </div>
</template>
