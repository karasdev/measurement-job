<script setup lang="ts">
definePageMeta({ layout: false })

const toast = useToast()
const email = ref('')
const password = ref('')
const loading = ref(false)

const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string

function isValidEmail(email: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

async function onSubmit() {
  if (!email.value?.trim()) {
    toast.add({ title: 'Validation error', description: 'Email is required', color: 'error' })
    return
  }
  if (!isValidEmail(email.value.trim())) {
    toast.add({ title: 'Validation error', description: "Please enter a valid email address (e.g. you@example.com)", color: 'error' })
    return
  }
  if (!password.value) {
    toast.add({ title: 'Validation error', description: 'Password is required', color: 'error' })
    return
  }
  loading.value = true
  try {
    const res = await $fetch<{ token: string; user: object }>(`${apiBase}/api/login`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: { email: email.value, password: password.value },
    })
    if (res.token) {
      useCookie('auth_token').value = res.token
      await navigateTo('/dashboard')
    }
  } catch (e: any) {
    const d = e?.data
    const msg = d?.message
      || (Array.isArray(d?.errors?.email) ? d.errors.email[0] : d?.errors?.email)
      || (Array.isArray(d?.errors?.password) ? d.errors.password[0] : d?.errors?.password)
      || e?.message
      || 'Login failed'
    toast.add({ title: 'Login failed', description: msg, color: 'error' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-gray-100 dark:bg-gray-900">
    <div class="w-full max-w-md">
      <UCard class="overflow-hidden">
        <template #header>
          <h1 class="text-2xl font-bold text-center text-gray-900 dark:text-white">Login</h1>
        </template>
        <form class="space-y-4" novalidate @submit.prevent="onSubmit">
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
              autocomplete="current-password"
            />
          </div>
          <UButton
            type="submit"
            block
            :loading="loading"
            :disabled="loading"
            color="primary"
          >
            {{ loading ? 'Signing in...' : 'Sign in' }}
          </UButton>
        </form>
        <template #footer>
          <p class="text-center text-sm text-gray-600 dark:text-gray-400">
            No account?
            <NuxtLink to="/register">
              <UButton color="primary" variant="link" size="sm">Register</UButton>
            </NuxtLink>
          </p>
        </template>
      </UCard>
    </div>
  </div>
</template>
