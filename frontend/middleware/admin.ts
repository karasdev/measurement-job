export default defineNuxtRouteMiddleware(async () => {
  const { fetch: apiFetch, token } = useApi()
  if (!token.value) {
    return navigateTo('/login')
  }
  try {
    const res = await apiFetch<{ user: { is_admin?: boolean } }>('/api/user')
    if (!res?.user?.is_admin) {
      return navigateTo('/dashboard')
    }
  } catch {
    return navigateTo('/login')
  }
})
