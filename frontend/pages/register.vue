<script setup lang="ts">
definePageMeta({ layout: false })

const toast = useToast()
const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)

const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string

function isValidEmail(email: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

async function onSubmit() {
  if (!name.value?.trim()) {
    toast.add({ title: 'Validation error', description: 'Name is required', color: 'error' })
    return
  }
  if (!email.value?.trim()) {
    toast.add({ title: 'Validation error', description: 'Email is required', color: 'error' })
    return
  }
  if (!isValidEmail(email.value.trim())) {
    toast.add({ title: 'Validation error', description: "Please enter a valid email address (e.g. you@example.com)", color: 'error' })
    return
  }
  if (!password.value || password.value.length < 8) {
    toast.add({ title: 'Validation error', description: 'Password must be at least 8 characters', color: 'error' })
    return
  }
  if (password.value !== passwordConfirmation.value) {
    toast.add({ title: 'Validation error', description: 'Passwords do not match', color: 'error' })
    return
  }
  loading.value = true
  try {
    const res = await $fetch<{ token: string; user: object }>(`${apiBase}/api/register`, {
      method: 'POST',
      body: {
        name: name.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      },
    })
    if (res.token) {
      useCookie('auth_token').value = res.token
      await navigateTo('/dashboard')
    }
  } catch (e: any) {
    const raw = e?.data?.message || e?.data?.errors
    const msg = typeof raw === 'object' ? Object.values(raw).flat().join(' ') : raw || 'Registration failed'
    toast.add({ title: 'Registration failed', description: msg, color: 'error' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-8 bg-gray-100 dark:bg-gray-900">
    <div class="w-full max-w-md">
      <UCard class="overflow-hidden">
        <template #header>
          <h1 class="text-2xl font-bold text-center text-gray-900 dark:text-white">Register</h1>
        </template>
        <form class="space-y-4" novalidate @submit.prevent="onSubmit">
          <div class="space-y-2">
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
            <UInput
              id="name"
              v-model="name"
              type="text"
              placeholder="Your name"
              class="w-full"
              autocomplete="name"
            />
          </div>
          <div class="space-y-2">
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Email</label>
            <UInput
              id="email"
              v-model="email"
              type="text"
              placeholder="you@example.com"
              class="w-full"
              autocomplete="email"
            />
          </div>
          <div class="space-y-2">
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Password</label>
            <UInput
              id="password"
              v-model="password"
              type="password"
              placeholder="••••••••"
              class="w-full"
              autocomplete="new-password"
            />
          </div>
          <div class="space-y-2">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Confirm password</label>
            <UInput
              id="password_confirmation"
              v-model="passwordConfirmation"
              type="password"
              placeholder="••••••••"
              class="w-full"
              autocomplete="new-password"
            />
          </div>
          <UButton
            type="submit"
            block
            :loading="loading"
            :disabled="loading"
            color="primary"
          >
            {{ loading ? 'Creating account...' : 'Register' }}
          </UButton>
        </form>
        <template #footer>
          <p class="text-center text-sm text-gray-600 dark:text-gray-400">
            Already have an account?
            <NuxtLink to="/login">
              <UButton color="primary" variant="link" size="sm">Login</UButton>
            </NuxtLink>
          </p>
        </template>
      </UCard>
    </div>
  </div>
</template>
