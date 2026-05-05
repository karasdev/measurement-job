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
const IN_PROGRESS_STATUSES = ['generating', 'processing', 'aggregating']

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
    // Backend now reuses the same job ID on retry; keep detail focused on the original job.
    await loadJobDetail(id)
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

function formatSeconds(ms: number | null) {
  if (ms == null) return '—'
  return `${(ms / 1000).toFixed(2)} s`
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const jobSummary = computed(() => {
  const list = jobs.value?.data ?? []
  const active = list.filter((job) => IN_PROGRESS_STATUSES.includes(job.status)).length
  const completed = list.filter((job) => job.status === 'completed').length
  const failed = list.filter((job) => job.status === 'failed' || job.status === 'partial').length
  const processed = list.reduce((sum, job) => sum + (job.rows_processed ?? 0), 0)
  const totalRows = list.reduce((sum, job) => sum + (job.requested_rows ?? 0), 0)
  const averageProgress = list.length
    ? Math.round(list.reduce((sum, job) => sum + displayProgress(job), 0) / list.length)
    : 0

  return { active, completed, failed, processed, totalRows, averageProgress }
})

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

let unsubscribeProgress: (() => void) | null = null
let smoothTimer: ReturnType<typeof setInterval> | null = null
let pollTimer: ReturnType<typeof setInterval> | null = null
const POLL_INTERVAL_MS = 1500

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

function startPollingJobDetail() {
  if (pollTimer) return
  pollTimer = setInterval(() => {
    const job = selectedJob.value
    if (!job?.id || !IN_PROGRESS_STATUSES.includes(job.status)) {
      if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
      }
      return
    }
    loadJobDetail(job.id)
  }, POLL_INTERVAL_MS)
}

function stopPollingJobDetail() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
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
    stopPollingJobDetail()
    if (id != null && status != null && IN_PROGRESS_STATUSES.includes(status)) {
      smoothedProgressPercent.value = displayProgress(selectedJob.value!)
      if (status === 'processing') startSmoothProgress()
      startPollingJobDetail()
    } else if (selectedJob.value) {
      smoothedProgressPercent.value = displayProgress(selectedJob.value)
    } else {
      smoothedProgressPercent.value = 0
    }
  },
  { immediate: true }
)

watch(
  () => jobs.value?.data?.map((j) => j.id) ?? [],
  (ids) => {
    if (unsubscribeProgress) {
      unsubscribeProgress()
      unsubscribeProgress = null
    }
    if (ids.length) {
      unsubscribeProgress = useJobProgress(ids, applyProgress)
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
  stopPollingJobDetail()
  if (unsubscribeProgress) unsubscribeProgress()
})

function logout() {
  token.value = null
  navigateTo('/login')
}
</script>

<template>
  <AppPageLayout title="Dashboard">
    <template #headerActions>
      <NuxtLink v-if="user?.is_admin" to="/admin">
        <UButton color="neutral" variant="ghost" size="sm">Admin</UButton>
      </NuxtLink>
      <span v-if="user" class="hidden sm:inline text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</span>
      <UButton color="neutral" variant="ghost" size="sm" @click="logout">
        Logout
      </UButton>
    </template>
    <div class="space-y-6">
      <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950">
        <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Measurement pipeline</p>
            <h2 class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">Process city temperature workloads</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">
              Generate rows, process chunks on queues, and review per-city min, max, and average temperatures with live progress.
            </p>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:min-w-[520px]">
            <div class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/70">
              <p class="text-xs text-gray-500 dark:text-gray-400">Active</p>
              <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ jobSummary.active }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/70">
              <p class="text-xs text-gray-500 dark:text-gray-400">Completed</p>
              <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ jobSummary.completed }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/70">
              <p class="text-xs text-gray-500 dark:text-gray-400">Processed</p>
              <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ jobSummary.processed.toLocaleString() }}</p>
            </div>
            <div class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/70">
              <p class="text-xs text-gray-500 dark:text-gray-400">Avg Progress</p>
              <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ jobSummary.averageProgress }}%</p>
            </div>
          </div>
        </div>
      </section>

      <UCard title="New job" description="Create a queued measurement workload. The minimum local run is 10,000 rows.">
        <form class="grid gap-4 sm:grid-cols-[minmax(220px,320px)_auto] sm:items-end" novalidate @submit.prevent="submitJob">
          <div class="flex flex-col gap-1.5">
            <label for="rows" class="text-sm font-medium text-gray-700 dark:text-gray-200">Number of rows</label>
            <UInput
              id="rows"
              v-model="rows"
              type="number"
              placeholder="50000"
              size="lg"
            />
          </div>
          <UButton type="submit" size="lg" :loading="submitting" :disabled="submitting" class="justify-center sm:w-32">
            {{ submitting ? 'Submitting' : 'Submit job' }}
          </UButton>
        </form>
      </UCard>

      <!-- Job list -->
      <UCard>
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Jobs</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ jobs?.total ?? 0 }} total jobs tracked for this account.</p>
            </div>
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
                {{ sortOrder === 'desc' ? 'Desc' : 'Asc' }}
              </UButton>
            </div>
          </div>
        </template>
        <div v-if="!jobs" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Loading jobs...</div>
        <div v-else-if="!jobs.data?.length" class="py-8 text-center text-muted">No jobs yet. Submit one above.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-[980px] w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th class="px-4 py-3">Job</th>
                <th class="px-4 py-3 text-right">Rows</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Phase</th>
                <th class="px-4 py-3 min-w-52">Progress</th>
                <th class="px-4 py-3 text-right">Runtime</th>
                <th class="px-4 py-3 text-right">Memory</th>
                <th class="px-4 py-3 text-right">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <tr
                v-for="job in jobs.data"
                :key="job.id"
                class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/70"
                @click="loadJobDetail(job.id)"
              >
                <td class="px-4 py-4">
                  <div class="font-mono font-medium text-gray-950 dark:text-white">#{{ job.id }}</div>
                  <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ job.completed_at ? 'Completed ' + formatDate(job.completed_at) : 'Open details' }}</div>
                </td>
                <td class="px-4 py-4 text-right font-medium text-gray-950 dark:text-white">{{ job.requested_rows.toLocaleString() }}</td>
                <td class="px-4 py-4">
                  <UBadge :color="statusBadgeColor(job.status)" variant="subtle" size="xs">{{ statusLabel(job.status) }}</UBadge>
                </td>
                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ phaseLabel(job.status) }}</td>
                <td class="px-4 py-4">
                  <div class="flex items-center justify-between gap-3">
                    <UProgress
                      :model-value="displayProgress(job)"
                      :max="100"
                      size="sm"
                      :color="job.status === 'completed' ? 'success' : job.status === 'partial' ? 'neutral' : job.status === 'failed' ? 'error' : 'primary'"
                    />
                    <span class="w-12 text-right font-medium text-gray-950 dark:text-white">{{ displayProgress(job) }}%</span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ job.rows_processed.toLocaleString() }} processed</p>
                </td>
                <td class="px-4 py-4 text-right text-gray-700 dark:text-gray-300">{{ formatSeconds(job.execution_time_ms) }}</td>
                <td class="px-4 py-4 text-right text-gray-700 dark:text-gray-300">{{ formatKbytes(job.memory_processing_bytes ?? job.memory_used_bytes) }}</td>
                <td class="px-4 py-4 text-right text-gray-500 dark:text-gray-400">{{ formatDate(job.created_at) }}</td>
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
            <div>
              <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Job #{{ selectedJob.id }}</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ selectedJob.requested_rows.toLocaleString() }} requested rows</p>
            </div>
            <div class="flex items-center gap-2">
              <UButton
                v-if="selectedJob.status === 'failed' || selectedJob.status === 'partial'"
                color="warning"
                size="sm"
                :loading="retrying"
                :disabled="retrying"
                @click="retryJob(selectedJob.id)"
              >
                {{ retrying ? 'Retrying' : 'Retry job' }}
              </UButton>
              <UButton color="neutral" variant="ghost" size="sm" @click="selectedJob = null">Close</UButton>
            </div>
          </div>
        </template>
        <div class="space-y-6">
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
              <p class="mt-2"><UBadge :color="statusBadgeColor(selectedJob.status)" variant="subtle" size="xs">{{ statusLabel(selectedJob.status) }}</UBadge></p>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Progress</p>
              <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ displayProgress(selectedJob) }}%</p>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Runtime</p>
              <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ formatSeconds(selectedJob.execution_time_ms) }}</p>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Memory</p>
              <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ formatKbytes(selectedJob.memory_processing_bytes ?? selectedJob.memory_used_bytes) }}</p>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cities</p>
              <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ selectedJob.temperature_results?.length?.toLocaleString() ?? '—' }}</p>
            </div>
          </div>
          <p v-if="selectedJob.error_message" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">{{ selectedJob.error_message }}</p>
          <div v-if="selectedJob.temperature_results?.length" class="space-y-6">
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div>
                  <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Temperature by city</h3>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Min, average, and max temperatures for the current city page.</p>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ chartPaginatedResults.total.toLocaleString() }} cities</span>
              </div>
              <ClientOnly>
                <div class="p-4">
                  <JobTemperatureChart :results="chartPaginatedResults.rows" />
                </div>
                <template #fallback>
                  <p class="p-4 text-sm text-gray-500 dark:text-gray-400">Loading chart...</p>
                </template>
              </ClientOnly>
              <div v-if="chartPaginatedResults.total > chartPerPage" class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 dark:border-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Showing {{ chartPaginatedResults.start }}-{{ chartPaginatedResults.end }} of {{ chartPaginatedResults.total }} cities
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
                  <span class="text-sm text-gray-500 dark:text-gray-400">
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
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
              <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">City results</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aggregated readings per city.</p>
              </div>
              <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/70">
                  <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3 text-left">City</th>
                    <th class="px-4 py-3 text-right">Min</th>
                    <th class="px-4 py-3 text-right">Max</th>
                    <th class="px-4 py-3 text-right">Avg</th>
                    <th class="px-4 py-3 text-right">Count</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                  <tr v-for="r in paginatedCityResults.rows" :key="r.city" class="hover:bg-gray-50 dark:hover:bg-gray-900/70">
                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ r.city }}</td>
                    <td class="px-4 py-3 text-sm text-right tabular-nums">{{ r.min_temp }}</td>
                    <td class="px-4 py-3 text-sm text-right tabular-nums">{{ r.max_temp }}</td>
                    <td class="px-4 py-3 text-sm text-right tabular-nums">{{ r.avg_temp }}</td>
                    <td class="px-4 py-3 text-sm text-right tabular-nums">{{ r.count.toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>
              </div>
              <div v-if="paginatedCityResults.total > cityTablePerPage" class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 dark:border-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Showing {{ paginatedCityResults.start }}-{{ paginatedCityResults.end }} of {{ paginatedCityResults.total }} cities
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
                  <span class="text-sm text-gray-500 dark:text-gray-400">
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
