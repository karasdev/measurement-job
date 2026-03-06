<template>
  <AppPageLayout title="Measurements Dashboard">
    <template #headerActions>
      <nav class="flex gap-4">
        <NuxtLink to="/login" class="text-gray-600 hover:text-gray-900">Login</NuxtLink>
        <NuxtLink to="/register" class="text-gray-600 hover:text-gray-900">Register</NuxtLink>
      </nav>
    </template>
    <p class="text-gray-600">
      Welcome to the measurements dashboard!
    </p>
  </AppPageLayout>
</template>
