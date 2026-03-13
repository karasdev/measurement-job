<script setup lang="ts">
definePageMeta({ middleware: 'auth' }) //check if user is logged in

const { fetch: apiFetch, token } = useApi() //useApi is a composable that fetches data from the API and provides the token for authentication
const toast = useToast()
const user = ref<{ id: number; name: string; email: string; is_admin?: boolean } | null>(null)
const jobs = ref<{ data: Job[]; current_page: number; last_page: number; total: number; per_page: number; from: number | null; to: number | null } | null>(null)
const jobsPage = ref(1)
const jobsPerPage = 15
const selectedJob = ref<Job | null>(null)
const rows = ref('50000')
const submitting = ref(false)
const statusFilter = ref('all')
const sortBy = ref('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')
const retrying = ref(false)
const cityTablePage = ref(1)
const cityTablePerPage = 20
const chartPage = ref(1)
const chartPerPage = 20
const logoutModalOpen = ref(false)
const realtimePinnedJobIds = ref<number[]>([])

function applyProgress(payload: {
  id: number
  status: string
  progress_percent: number
  rows_processed: number
  execution_time_ms: number | null
  memory_used_bytes: number | null
  error_message: string | null
  completed_at: string | null
}) {
  if (jobs.value?.data) {
    const j = jobs.value.data.find((job) => job.id === payload.id)
    if (j) {
      j.status = payload.status
      j.progress_percent = payload.progress_percent
      j.rows_processed = payload.rows_processed
      j.execution_time_ms = payload.execution_time_ms
      j.memory_used_bytes = payload.memory_used_bytes
      j.error_message = payload.error_message
      j.completed_at = payload.completed_at
    }
  }
  if (selectedJob.value?.id === payload.id) {
    selectedJob.value = { ...selectedJob.value, ...payload }
    if (payload.status === 'completed' || payload.status === 'partial') loadJobDetail(payload.id)
  }
  if (payload.status === 'completed' || payload.status === 'partial' || payload.status === 'failed') {
    realtimePinnedJobIds.value = realtimePinnedJobIds.value.filter((id) => id !== payload.id)
  }
}

interface Job {
  id: number
  requested_rows: number
  status: string
  progress_percent: number
  rows_processed: number
  execution_time_ms: number | null
  memory_used_bytes: number | null
  memory_generating_bytes?: number | null
  memory_processing_bytes?: number | null
  memory_aggregate_bytes?: number | null
  memory_total_bytes?: number | null
  error_message: string | null
  completed_at: string | null
  created_at: string
  temperature_results?: { city: string; min_temp: number; max_temp: number; avg_temp: number; count: number }[]
}

async function loadUser() {
  try {
    const res = await apiFetch<{ user: { id: number; name: string; email: string; is_admin?: boolean } }>('/api/user')
    user.value = res.user
  } catch {
    token.value = null
    await navigateTo('/login')
  }
}

async function loadJobs() {
  try {
    const params = new URLSearchParams()
    if (statusFilter.value && statusFilter.value !== 'all') params.set('status', statusFilter.value)
    params.set('sort', sortBy.value)
    params.set('order', sortOrder.value)
    params.set('page', String(jobsPage.value))
    params.set('per_page', String(jobsPerPage))
    const res = await apiFetch<{ data: Job[]; current_page: number; last_page: number; total: number; per_page: number; from: number | null; to: number | null }>('/api/jobs?' + params.toString())
    jobs.value = res
    if (res?.data?.length) {
      const terminalIds = new Set(
        res.data
          .filter((j) => j.status === 'completed' || j.status === 'partial' || j.status === 'failed')
          .map((j) => j.id)
      )
      if (terminalIds.size) {
        realtimePinnedJobIds.value = realtimePinnedJobIds.value.filter((id) => !terminalIds.has(id))
      }
    }
  } catch {
    jobs.value = null
  }
}

function setJobsPage(p: number) {
  const last = jobs.value?.last_page ?? 1
  jobsPage.value = Math.max(1, Math.min(p, last))
  loadJobs()
}

function onJobsFilterOrSortChange() {
  jobsPage.value = 1
  loadJobs()
}

async function retryJob(id: number) {
  retrying.value = true
  try {
    const res = await apiFetch<{ job_id: number }>('/api/jobs/' + id + '/retry', { method: 'POST' })
    jobsPage.value = 1
    await loadJobs()
    if (res.job_id) await loadJobDetail(res.job_id)
  } catch (e: any) {
    const msg = e?.data?.message || e?.message || 'Retry failed.'
    toast.add({ title: 'Retry failed', description: msg, color: 'error' })
  } finally {
    retrying.value = false
  }
}

async function loadJobDetail(id: number) {
  try {
    const res = await apiFetch<Job>('/api/jobs/' + id)
    selectedJob.value = res
    // Keep the "Your jobs" table in sync so list rows show the same progress as the detail bar
    if (jobs.value?.data) {
      const j = jobs.value.data.find((job) => job.id === res.id)
      if (j) {
        j.status = res.status
        j.progress_percent = res.progress_percent
        j.rows_processed = res.rows_processed
        j.execution_time_ms = res.execution_time_ms
        j.memory_used_bytes = res.memory_used_bytes
        j.memory_generating_bytes = res.memory_generating_bytes
        j.memory_processing_bytes = res.memory_processing_bytes
        j.memory_aggregate_bytes = res.memory_aggregate_bytes
        j.memory_total_bytes = res.memory_total_bytes
        j.error_message = res.error_message
        j.completed_at = res.completed_at
      }
    }
  } catch {
    selectedJob.value = null
  }
}

async function submitJob() {
  const normalized = String(rows.value).replace(/[_,\s]/g, '')
  const num = parseInt(normalized, 10)
  if (Number.isNaN(num) || num < 10000) {
    toast.add({ title: 'Invalid input', description: 'Enter at least 10,000 rows.', color: 'error' })
    return
  }
  if (num > 1000000000) {
    toast.add({ title: 'Invalid input', description: 'Maximum 1,000,000,000 rows.', color: 'error' })
    return
  }
  submitting.value = true
  try {
    const res = await apiFetch<{ job_id: number }>('/api/jobs', {
      method: 'POST',
      body: { rows: num },
    })
    if (res?.job_id) {
      const ids = new Set(realtimePinnedJobIds.value)
      ids.add(res.job_id)
      realtimePinnedJobIds.value = [...ids]
    }
    jobsPage.value = 1
    await loadJobs()
    // Don't open job detail automatically while job is generating
  } catch (e: any) {
    const data = e?.data
    if (e?.statusCode === 401) {
      token.value = null
      await navigateTo('/login')
      return
    }
    const msg = data?.message
      || (Array.isArray(data?.errors?.rows) ? data.errors.rows[0] : data?.errors?.rows)
      || (typeof data?.errors === 'object' ? JSON.stringify(data.errors) : null)
      || data?.error
      || e?.message
      || 'Failed to submit job. Check backend is running (php artisan serve) and try again.'
    const displayMsg = (data?.message && data?.error) ? `${data.message} ${data.error}` : msg
    toast.add({ title: 'Job submission failed', description: displayMsg, color: 'error' })
  } finally {
    submitting.value = false
  }
}

/** Progress never exceeds row-based % so e.g. 98M/100M shows 98% not 100%. Completed: 100% only if we processed all requested rows; otherwise show actual %. */
function displayProgress(job: { status?: string; progress_percent: number; rows_processed: number; requested_rows: number }) {
  if (!job) return 0
  const rowPct = job.requested_rows > 0 ? Math.round((job.rows_processed / job.requested_rows) * 100) : 100
  if (job.status === 'completed' || job.status === 'partial') return Math.min(rowPct, 100)
  return Math.min(job.progress_percent, rowPct, 100)
}

function statusColor(status: string) {
  switch (status) {
    case 'completed':
      return 'bg-green-100 text-green-800'
    case 'partial':
      return 'bg-amber-100 text-amber-800'
    case 'failed':
      return 'bg-red-100 text-red-800'
    case 'processing':
    case 'generating':
    case 'aggregating':
      return 'bg-blue-100 text-blue-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

function statusBadgeColor(status: string): 'success' | 'error' | 'primary' | 'neutral' {
  switch (status) {
    case 'completed': return 'success'
    case 'partial': return 'neutral'
    case 'failed': return 'error'
    case 'processing':
    case 'generating':
    case 'aggregating': return 'primary'
    default: return 'neutral'
  }
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    completed: 'Completed',
    partial: 'Partial',
    failed: 'Failed',
    pending: 'Pending',
    generating: 'Generating',
    processing: 'Processing',
    aggregating: 'Aggregating',
  }
  return labels[status] ?? status
}

function phaseLabel(status: string): string {
  const phases: Record<string, string> = {
    generating: 'Generating file',
    processing: 'Processing chunks',
    aggregating: 'Aggregating',
    pending: 'Waiting',
    completed: 'Completed',
    partial: 'Partial',
    failed: 'Failed',
  }
  return phases[status] ?? status
}

function formatBytes(bytes: number | null) {
  if (bytes == null) return '—'
  const n = Math.round(bytes)
  return n.toLocaleString() + ' B'
}

function formatKbytes(bytes: number | null) {
  if (bytes == null) return '—'
  const kb = bytes / 1024
  return kb.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' KB'
}

const paginatedCityResults = computed(() => {
  const list = selectedJob.value?.temperature_results ?? []
  const total = list.length
  const perPage = cityTablePerPage
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const page = Math.min(Math.max(1, cityTablePage.value), totalPages)
  const start = (page - 1) * perPage
  return { rows: list.slice(start, start + perPage), page, totalPages, total, start: start + 1, end: Math.min(start + perPage, total) }
})

function setCityTablePage(p: number) {
  cityTablePage.value = Math.max(1, Math.min(p, paginatedCityResults.value.totalPages))
}

const chartPaginatedResults = computed(() => {
  const list = selectedJob.value?.temperature_results ?? []
  const total = list.length
  const perPage = chartPerPage
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const page = Math.min(Math.max(1, chartPage.value), totalPages)
  const start = (page - 1) * perPage
  return { rows: list.slice(start, start + perPage), page, totalPages, total, start: start + 1, end: Math.min(start + perPage, total) }
})

function setChartPage(p: number) {
  chartPage.value = Math.max(1, Math.min(p, chartPaginatedResults.value.totalPages))
}

const SMOOTH_TICK_MS = 120
const SMOOTH_STEP = 4
const RESYNC_THROTTLE_MS = 1000
const IN_PROGRESS_STATUSES = ['generating', 'processing', 'aggregating']

let unsubscribeProgress: (() => void) | null = null
let smoothTimer: ReturnType<typeof setInterval> | null = null
let syncInFlight = false
let lastSyncAt = 0

const smoothedProgressPercent = ref(0)

function startSmoothProgress() {
  if (smoothTimer) return
  smoothTimer = setInterval(() => {
    const job = selectedJob.value
    if (!job || !IN_PROGRESS_STATUSES.includes(job.status)) {
      if (smoothTimer) {
        clearInterval(smoothTimer)
        smoothTimer = null
      }
      return
    }
    const target = displayProgress(job)
    const current = smoothedProgressPercent.value
    if (current < target) {
      smoothedProgressPercent.value = Math.min(current + SMOOTH_STEP, target)
    } else if (current > target) {
      smoothedProgressPercent.value = Math.max(current - SMOOTH_STEP, target)
    }
  }, SMOOTH_TICK_MS)
}

function stopSmoothProgress() {
  if (smoothTimer) {
    clearInterval(smoothTimer)
    smoothTimer = null
  }
}

async function syncJobsFromServer() {
  if (syncInFlight) return
  if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return

  const now = Date.now()
  if (now - lastSyncAt < RESYNC_THROTTLE_MS) return
  lastSyncAt = now

  syncInFlight = true
  try {
    await loadJobs()
    const job = selectedJob.value
    if (job?.id && IN_PROGRESS_STATUSES.includes(job.status)) {
      await loadJobDetail(job.id)
    }
  } finally {
    syncInFlight = false
  }
}

watch(selectedJob, () => {
  cityTablePage.value = 1
  chartPage.value = 1
})

watch(
  () => ({ id: selectedJob.value?.id, status: selectedJob.value?.status }),
  ({ id, status }) => {
    stopSmoothProgress()
    if (id != null && status != null && IN_PROGRESS_STATUSES.includes(status)) {
      smoothedProgressPercent.value = displayProgress(selectedJob.value!)
      startSmoothProgress()
    } else if (selectedJob.value) {
      smoothedProgressPercent.value = displayProgress(selectedJob.value)
    } else {
      smoothedProgressPercent.value = 0
    }
  },
  { immediate: true }
)

watch(
  () => {
    const ids = new Set<number>(jobs.value?.data?.map((j) => j.id) ?? [])
    if (selectedJob.value?.id) ids.add(selectedJob.value.id)
    for (const id of realtimePinnedJobIds.value) ids.add(id)
    return [...ids].sort((a, b) => a - b).join(',')
  },
  (idStr) => {
    if (unsubscribeProgress) {
      unsubscribeProgress()
      unsubscribeProgress = null
    }
    const ids = idStr ? idStr.split(',').map(Number) : []
    if (ids.length) {
      unsubscribeProgress = useJobProgress(ids, applyProgress, syncJobsFromServer)
    }
  },
  { immediate: true }
)

function onPageVisible() {
  if (typeof document === 'undefined' || document.visibilityState !== 'visible') return
  const job = selectedJob.value
  if (job?.id) loadJobDetail(job.id)
  loadJobs()
}

onMounted(async () => {
  await loadUser()
  await loadJobs()
  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', onPageVisible)
  }
})

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.removeEventListener('visibilitychange', onPageVisible)
  }
  stopSmoothProgress()
  if (unsubscribeProgress) unsubscribeProgress()
})

async function logout() {
  logoutModalOpen.value = false

  try {
    await apiFetch('/api/logout', { method: 'POST' })
  } catch {
    // Continue local logout even if token is already invalid server-side.
  } finally {
    token.value = null
    await navigateTo('/login')
  }
}
</script>

<template>
  <AppPageLayout title="Dashboard">
    <template #headerActions>
      <NuxtLink v-if="user?.is_admin" to="/admin">
        <UButton color="neutral" variant="link" size="sm">Admin</UButton>
      </NuxtLink>
      <span v-if="user" class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ user.name }}</span>
      <UButton
        color="error"
        variant="solid"
        size="sm"
        icon="i-lucide-log-out"
        class="font-semibold shadow-sm"
        @click="logoutModalOpen = true"
      >
        Logout
      </UButton>
    </template>

    <UModal
      v-model:open="logoutModalOpen"
      title="Confirm logout"
      description="Are you sure you want to logout?"
    >
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton color="neutral" variant="soft" @click="logoutModalOpen = false">
            Cancel
          </UButton>
          <UButton color="error" variant="solid" icon="i-lucide-log-out" @click="logout">
            Logout
          </UButton>
        </div>
      </template>
    </UModal>

    <div class="space-y-8">
      <!-- Submit job form -->
      <UCard title="New job" description="Generate a measurements file and compute min/max/avg temperature per city. Local: min 10,000 rows. Production: 100M–1B.">
        <form class="flex flex-wrap items-end gap-4" novalidate @submit.prevent="submitJob">
          <div class="flex flex-col gap-1.5 w-48">
            <label for="rows" class="text-sm font-medium">Number of rows</label>
            <UInput
              id="rows"
              v-model="rows"
              type="number"
              placeholder="e.g. 50000"
            />
          </div>
          <UButton type="submit" :loading="submitting" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit job' }}
          </UButton>
        </form>
      </UCard>

      <!-- Job list -->
      <UCard>
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-lg font-medium">Your jobs</h2>
            <div class="flex flex-wrap items-center gap-3">
              <USelect
                v-model="statusFilter"
                :items="[
                  { label: 'All statuses', value: 'all' },
                  { label: 'Pending', value: 'pending' },
                  { label: 'Generating', value: 'generating' },
                  { label: 'Processing', value: 'processing' },
                  { label: 'Completed', value: 'completed' },
                  { label: 'Failed', value: 'failed' },
                ]"
                value-key="value"
                class="w-40"
                @update:model-value="onJobsFilterOrSortChange"
              />
              <USelect
                v-model="sortBy"
                :items="[
                  { label: 'Date', value: 'created_at' },
                  { label: 'Status', value: 'status' },
                  { label: 'Progress', value: 'progress_percent' },
                  { label: 'Rows requested', value: 'requested_rows' },
                ]"
                value-key="value"
                class="w-40"
                @update:model-value="onJobsFilterOrSortChange"
              />
              <UButton color="neutral" variant="soft" size="sm" @click="sortOrder = sortOrder === 'desc' ? 'asc' : 'desc'; onJobsFilterOrSortChange()">
                {{ sortOrder === 'desc' ? '↓ Desc' : '↑ Asc' }}
              </UButton>
            </div>
          </div>
        </template>
        <div v-if="!jobs" class="py-8 text-center text-muted">Loading…</div>
        <div v-else-if="!jobs.data?.length" class="py-8 text-center text-muted">No jobs yet. Submit one above.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-muted text-left">
                <th class="px-4 py-2 font-medium w-20 text-center">#</th>
                <th class="px-4 py-2 font-medium text-center">Rows</th>
                <!-- <th class="px-4 py-2 font-medium text-center">Status</th> -->
                <th class="px-4 py-2 font-medium text-center">Phase</th>
                <th class="px-4 py-2 font-medium w-28 text-center">Progress bar</th>
                <!-- <th class="px-4 py-2 font-medium text-center">Execution time</th>
                <th class="px-4 py-2 font-medium text-center">Memory</th> -->
                <th class="px-4 py-2 font-medium text-center">Progress</th>
                <th class="px-4 py-2 font-medium text-center">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="job in jobs.data"
                :key="job.id"
                class="border-b border-border hover:bg-muted/50 cursor-pointer"
                @click="loadJobDetail(job.id)"
              >
                <td class="px-4 py-3 font-mono text-muted text-center">#{{ job.id }}</td>
                <td class="px-4 py-3 text-center">{{ job.requested_rows.toLocaleString() }}</td>
                <!-- <td class="px-4 py-3 text-center">
                  <UBadge :color="statusBadgeColor(job.status)" variant="subtle" size="xs">{{ statusLabel(job.status) }}</UBadge>
                </td> -->
                <td class="px-4 py-3 text-center">{{ phaseLabel(job.status) }}</td>
                <td class="px-4 py-3 w-28 text-center">
                  <UProgress
                    :model-value="displayProgress(job)"
                    :max="100"
                    size="xs"
                    :color="job.status === 'completed' ? 'success' : job.status === 'partial' ? 'neutral' : 'primary'"
                  />
                </td>
                <!-- <td class="px-4 py-3 text-center">{{ job.execution_time_ms != null ? (job.execution_time_ms / 1000).toFixed(2) + ' s' : '—' }}</td>
                <td class="px-4 py-3 text-center">{{ formatKbytes(job.memory_processing_bytes ?? null) }}</td> -->
                <td class="px-4 py-3 text-center">
                  <span v-if="job.status !== 'pending'">{{ displayProgress(job) }}% · {{ job.rows_processed.toLocaleString() }} processed</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="px-4 py-3 text-right text-muted text-center">{{ new Date(job.created_at).toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="jobs && jobs.last_page > 1"
          class="px-4 py-3 border-t flex flex-wrap items-center justify-between gap-2 bg-muted/30"
        >
          <p class="text-sm text-muted">
            <template v-if="jobs.from != null && jobs.to != null">
              Showing {{ jobs.from }}–{{ jobs.to }} of {{ jobs.total }} jobs
            </template>
            <template v-else>
              Page {{ jobs.current_page }} of {{ jobs.last_page }}
            </template>
          </p>
          <div class="flex items-center gap-2">
            <UButton
              color="neutral"
              variant="outline"
              size="sm"
              :disabled="jobs.current_page <= 1"
              @click="setJobsPage(jobs.current_page - 1)"
            >
              Previous
            </UButton>
            <span class="text-sm text-muted">
              Page {{ jobs.current_page }} of {{ jobs.last_page }}
            </span>
            <UButton
              color="neutral"
              variant="outline"
              size="sm"
              :disabled="jobs.current_page >= jobs.last_page"
              @click="setJobsPage(jobs.current_page + 1)"
            >
              Next
            </UButton>
          </div>
        </div>
      </UCard>

      <!-- Job detail (selected) -->
      <UCard v-if="selectedJob">
        <template #header>
          <div class="flex justify-between items-center flex-wrap gap-2">
            <h2 class="text-lg font-medium">Job #{{ selectedJob.id }}</h2>
            <div class="flex items-center gap-2">
              <UButton
                v-if="selectedJob.status === 'failed' || selectedJob.status === 'partial'"
                color="warning"
                size="sm"
                :loading="retrying"
                :disabled="retrying"
                @click="retryJob(selectedJob.id)"
              >
                {{ retrying ? 'Retrying…' : 'Retry job' }}
              </UButton>
              <UButton color="neutral" variant="ghost" size="sm" @click="selectedJob = null">Close</UButton>
            </div>
          </div>
        </template>
        <div class="space-y-4">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
              <span class="text-muted">Status</span>
              <p class="font-medium">
                <UBadge :color="statusBadgeColor(selectedJob.status)" variant="subtle" size="xs">{{ statusLabel(selectedJob.status) }}</UBadge>
              </p>
            </div>
            <div>
              <span class="text-muted">Progress</span>
              <p class="font-medium">{{ displayProgress(selectedJob) }}%</p>
            </div>
            <div>
              <span class="text-muted">Execution time</span>
              <p class="font-medium">{{ selectedJob.execution_time_ms != null ? (selectedJob.execution_time_ms / 1000).toFixed(2) + ' s' : '—' }}</p>
            </div>
            <div>
              <span class="text-muted">Memory</span>
              <p class="font-medium">{{ formatKbytes(selectedJob.memory_processing_bytes) }}</p>
            </div>
          </div>
          <p v-if="selectedJob.error_message" class="text-error text-sm">{{ selectedJob.error_message }}</p>
          <div v-if="selectedJob.temperature_results?.length" class="space-y-4">
            <div class="border rounded-lg overflow-hidden">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 px-4 pt-3 pb-2 bg-muted/30">Temperature by city (chart)</h3>
              <ClientOnly>
                <div class="px-4 pb-2">
                  <JobTemperatureChart :results="chartPaginatedResults.rows" />
                </div>
                <template #fallback>
                  <p class="text-sm text-gray-500 px-4 pb-2">Loading chart…</p>
                </template>
              </ClientOnly>
              <div v-if="chartPaginatedResults.total > chartPerPage" class="px-4 py-3 border-t flex flex-wrap items-center justify-between gap-2 bg-muted/30">
                <p class="text-sm text-muted">
                  Showing {{ chartPaginatedResults.start }}–{{ chartPaginatedResults.end }} of {{ chartPaginatedResults.total }} cities
                </p>
                <div class="flex items-center gap-2">
                  <UButton
                    color="neutral"
                    variant="outline"
                    size="sm"
                    :disabled="chartPaginatedResults.page <= 1"
                    @click="setChartPage(chartPaginatedResults.page - 1)"
                  >
                    Previous
                  </UButton>
                  <span class="text-sm text-muted">
                    Page {{ chartPaginatedResults.page }} of {{ chartPaginatedResults.totalPages }}
                  </span>
                  <UButton
                    color="neutral"
                    variant="outline"
                    size="sm"
                    :disabled="chartPaginatedResults.page >= chartPaginatedResults.totalPages"
                    @click="setChartPage(chartPaginatedResults.page + 1)"
                  >
                    Next
                  </UButton>
                </div>
              </div>
            </div>
            <div class="border rounded-lg overflow-x-auto">
              <table class="min-w-full divide-y divide-border">
                <thead class="bg-muted/30">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-muted uppercase">City</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-muted uppercase">Min</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-muted uppercase">Max</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-muted uppercase">Avg</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-muted uppercase">Count</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <tr v-for="r in paginatedCityResults.rows" :key="r.city">
                    <td class="px-4 py-2 text-sm">{{ r.city }}</td>
                    <td class="px-4 py-2 text-sm text-right">{{ r.min_temp }}</td>
                    <td class="px-4 py-2 text-sm text-right">{{ r.max_temp }}</td>
                    <td class="px-4 py-2 text-sm text-right">{{ r.avg_temp }}</td>
                    <td class="px-4 py-2 text-sm text-right">{{ r.count.toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>
              <div v-if="paginatedCityResults.total > cityTablePerPage" class="px-4 py-3 border-t flex flex-wrap items-center justify-between gap-2 bg-muted/30">
                <p class="text-sm text-muted">
                  Showing {{ paginatedCityResults.start }}–{{ paginatedCityResults.end }} of {{ paginatedCityResults.total }} cities
                </p>
                <div class="flex items-center gap-2">
                  <UButton
                    color="neutral"
                    variant="outline"
                    size="sm"
                    :disabled="paginatedCityResults.page <= 1"
                    @click="setCityTablePage(paginatedCityResults.page - 1)"
                  >
                    Previous
                  </UButton>
                  <span class="text-sm text-muted">
                    Page {{ paginatedCityResults.page }} of {{ paginatedCityResults.totalPages }}
                  </span>
                  <UButton
                    color="neutral"
                    variant="outline"
                    size="sm"
                    :disabled="paginatedCityResults.page >= paginatedCityResults.totalPages"
                    @click="setCityTablePage(paginatedCityResults.page + 1)"
                  >
                    Next
                  </UButton>
                </div>
              </div>
            </div>
          </div>
        </div>
      </UCard>
    </div>
  </AppPageLayout>
</template>
