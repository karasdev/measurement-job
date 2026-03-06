<script setup lang="ts">
definePageMeta({ middleware: ['auth', 'admin'] })

const { fetch: apiFetch } = useApi()
const activeTab = ref<'stats' | 'jobs' | 'users'>('stats')
const stats = ref<{ total_jobs: number; total_users: number; jobs_by_status: Record<string, number> } | null>(null)
const adminJobs = ref<{ data: AdminJob[]; current_page: number; last_page: number } | null>(null)
const adminUsers = ref<{ data: AdminUser[]; current_page: number; last_page: number } | null>(null)

interface AdminJob {
  id: number
  user_id: number
  user?: { id: number; name: string; email: string }
  requested_rows: number
  status: string
  progress_percent: number
  rows_processed: number
  created_at: string
}

interface AdminUser {
  id: number
  name: string
  email: string
  is_admin: boolean
  created_at: string
  measurement_jobs_count: number
}

async function loadStats() {
  try {
    stats.value = await apiFetch('/api/admin/stats')
  } catch {
    stats.value = null
  }
}

async function loadAdminJobs() {
  try {
    adminJobs.value = await apiFetch('/api/admin/jobs')
  } catch {
    adminJobs.value = null
  }
}

async function loadAdminUsers() {
  try {
    adminUsers.value = await apiFetch('/api/admin/users')
  } catch {
    adminUsers.value = null
  }
}

function statusBadgeColor(status: string): 'success' | 'error' | 'primary' | 'neutral' {
  switch (status) {
    case 'completed': return 'success'
    case 'failed': return 'error'
    case 'processing':
    case 'generating': return 'primary'
    default: return 'neutral'
  }
}

watch(activeTab, (tab) => {
  if (tab === 'stats') loadStats()
  if (tab === 'jobs') loadAdminJobs()
  if (tab === 'users') loadAdminUsers()
}, { immediate: true })
</script>

<template>
  <AppPageLayout title="Admin">
    <template #headerActions>
      <NuxtLink to="/dashboard">
        <UButton color="neutral" variant="link" size="sm">← Dashboard</UButton>
      </NuxtLink>
    </template>
    <div class="space-y-6">
      <div class="flex gap-2 border-b border-border pb-2">
        <UButton
          :color="activeTab === 'stats' ? 'primary' : 'neutral'"
          :variant="activeTab === 'stats' ? 'solid' : 'soft'"
          size="sm"
          @click="activeTab = 'stats'"
        >
          Stats
        </UButton>
        <UButton
          :color="activeTab === 'jobs' ? 'primary' : 'neutral'"
          :variant="activeTab === 'jobs' ? 'solid' : 'soft'"
          size="sm"
          @click="activeTab = 'jobs'"
        >
          All jobs
        </UButton>
        <UButton
          :color="activeTab === 'users' ? 'primary' : 'neutral'"
          :variant="activeTab === 'users' ? 'solid' : 'soft'"
          size="sm"
          @click="activeTab = 'users'"
        >
          Users
        </UButton>
      </div>

      <!-- Stats -->
      <UCard v-if="activeTab === 'stats'">
        <div v-if="!stats" class="py-8 text-center text-muted">Loading…</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="border border-border rounded-lg p-4">
            <p class="text-sm text-muted">Total jobs</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total_jobs.toLocaleString() }}</p>
          </div>
          <div class="border border-border rounded-lg p-4">
            <p class="text-sm text-muted">Total users</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total_users.toLocaleString() }}</p>
          </div>
          <div class="border border-border rounded-lg p-4 sm:col-span-2">
            <p class="text-sm text-muted mb-2">Jobs by status</p>
            <div class="flex flex-wrap gap-2">
              <UBadge
                v-for="(count, status) in stats.jobs_by_status"
                :key="status"
                :color="statusBadgeColor(status)"
                variant="subtle"
                size="xs"
              >
                {{ status }}: {{ count }}
              </UBadge>
            </div>
          </div>
        </div>
      </UCard>

      <!-- All jobs -->
      <UCard v-if="activeTab === 'jobs'">
        <template #header>
          <h2 class="text-lg font-medium">All jobs</h2>
        </template>
        <div v-if="!adminJobs" class="py-8 text-center text-muted">Loading…</div>
        <div v-else-if="!adminJobs.data?.length" class="py-8 text-center text-muted">No jobs.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-border">
            <thead class="bg-muted/30">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">User</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-muted uppercase">Rows</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Status</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="job in adminJobs.data" :key="job.id" class="hover:bg-muted/50">
                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-white">{{ job.id }}</td>
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ job.user?.email ?? job.user_id }}</td>
                <td class="px-4 py-2 text-sm text-right">{{ job.requested_rows.toLocaleString() }}</td>
                <td class="px-4 py-2">
                  <UBadge :color="statusBadgeColor(job.status)" variant="subtle" size="xs">{{ job.status }}</UBadge>
                </td>
                <td class="px-4 py-2 text-sm text-muted">{{ new Date(job.created_at).toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

      <!-- Users -->
      <UCard v-if="activeTab === 'users'">
        <template #header>
          <h2 class="text-lg font-medium">Users</h2>
        </template>
        <div v-if="!adminUsers" class="py-8 text-center text-muted">Loading…</div>
        <div v-else-if="!adminUsers.data?.length" class="py-8 text-center text-muted">No users.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-border">
            <thead class="bg-muted/30">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Email</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-muted uppercase">Jobs</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Admin</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Joined</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="u in adminUsers.data" :key="u.id" class="hover:bg-muted/50">
                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-white">{{ u.id }}</td>
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ u.name }}</td>
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ u.email }}</td>
                <td class="px-4 py-2 text-sm text-right">{{ u.measurement_jobs_count }}</td>
                <td class="px-4 py-2">
                  <UBadge v-if="u.is_admin" color="primary" variant="subtle" size="xs">Admin</UBadge>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="px-4 py-2 text-sm text-muted">{{ new Date(u.created_at).toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>
    </div>
  </AppPageLayout>
</template>
