# Code Walkthrough – Approach, Functions, and Frontend Usage

This document gives a **walkthrough of the code**: what approach was taken and why, what every important function does, and what is used in the frontend. Use it together with **guide.md** (flow) and **APPROACH_AND_REASONS.md** (design decisions).

---

## Overall approach

- **Backend:** Laravel API with Sanctum (token in cookie). Heavy work (generate file, process chunks, aggregate) runs in **queue jobs**, not in the HTTP request. Optional **Pusher** broadcasts progress so the frontend can update in real time.
- **Frontend:** Nuxt 4 (Vue 3), Nuxt UI. One dashboard page: **Your jobs** table (columns: #, Rows, Status, Phase, Progress bar, Execution time, Memory in KB, Progress text, Date) with **server-side pagination** (15 per page). Submitting a job does **not** auto-open the detail panel. Selected job detail: grid (Status, Progress %, Execution time, Memory in KB); **Retry** for failed or partial; **chart** and **temperature-by-city table** with **client-side pagination** (20 per page). **useApi** for all API calls. **useJobProgress** (Reverb default or Pusher) for real-time; list refetches when a job completes or is partial so Memory updates; **polling** when selected job is in progress. **Notifications:** toasts for errors only.
- **Auth:** Cookie `auth_token`; middleware checks it before dashboard/admin; **useApi** sends `Authorization: Bearer <token>` on every request.

---

## Frontend

### 1. `frontend/pages/dashboard.vue`

**Approach:** Single page: load user + jobs on mount; user can submit a new job, filter/sort the list, click a job to see detail. Real-time progress is applied via a callback so the list and detail update without refresh.

**Page guard and API:**

| Line / block | Meaning |
|--------------|--------|
| `definePageMeta({ middleware: 'auth' })` | Only logged-in users can open this page; auth middleware redirects to login if no token. |
| `const { fetch: apiFetch, token } = useApi()` | Gets the API client and the auth token ref; all API calls use `apiFetch`, logout/401 set `token.value = null`. |

**State (refs):**

| Variable | Purpose |
|----------|--------|
| `user` | Current user from `/api/user`; used in header (email, Admin link). |
| `jobs` | Paginated job list from `/api/jobs` (includes `data`, `current_page`, `last_page`, `total`, `from`, `to`); drives “Your jobs” list. |
| `selectedJob` | The one job currently shown in the detail panel; `null` when none selected. |
| `jobsPage`, `jobsPerPage` (15) | Current page and page size for “Your jobs”; `loadJobs()` sends `page` and `per_page`; `setJobsPage(p)` and `onJobsFilterOrSortChange()` handle pagination and reset-to-page-1. |
| `rows` | Input for “Number of rows” when submitting a new job. |
| `submitting` | True while submit request is in progress (disables button). |
| `statusFilter`, `sortBy`, `sortOrder` | Filter and sort for the job list; changing them calls `onJobsFilterOrSortChange()` (page 1 + `loadJobs()`). |
| `retrying` | True while a “Retry job” request is in progress. |
| `cityTablePage`, `cityTablePerPage` (20) | Client-side pagination for the temperature-by-city table; `paginatedCityResults`, `setCityTablePage`. |
| `chartPage`, `chartPerPage` (20) | Client-side pagination for the chart; `chartPaginatedResults`, `setChartPage`. |
| `smoothedProgressPercent` | Smoothed value for the progress bar during **processing** (animates toward actual %); indeterminate bar used for **generating** / **aggregating**. |

**Functions:**

| Function | What it does | Used where |
|----------|--------------|------------|
| **applyProgress(payload)** | Receives a progress event from real time; updates the matching job in `jobs.value.data`; when status is **completed** or **partial**, calls `loadJobs()` to refetch the list (so Memory column is correct) and, if that job is selected, updates `selectedJob` and calls `loadJobDetail`. | Passed to `useJobProgress(ids, applyProgress)` in the watch. |
| **loadUser()** | Calls `apiFetch('/api/user')`, sets `user.value`. On failure, clears token and redirects to login. | `onMounted`. Header uses `user`. |
| **loadJobs()** | Builds query from `statusFilter`, `sortBy`, `sortOrder`, **page**, **per_page**; calls `apiFetch('/api/jobs?...')`; sets `jobs.value`. | `onMounted`, after submit (with page 1), after retry (page 1), and when filter/sort or page changes. |
| **loadJobDetail(id)** | Fetches `GET /api/jobs/:id`, sets `selectedJob.value`, and **updates the same job in `jobs.value.data`** so the list row stays in sync. | Click on a list row; from `applyProgress` when job completes or is partial (and that job is selected); from `retryJob`; called by **polling** when the selected job is in progress. Not called after `submitJob` (detail does not auto-open). |
| **setJobsPage(p)** | Sets `jobsPage` to p (clamped to 1..last_page), calls `loadJobs()`. | Previous/Next under “Your jobs” list. |
| **onJobsFilterOrSortChange()** | Sets `jobsPage` to 1, calls `loadJobs()`. | When status filter, sort by, or sort order changes. |
| **retryJob(id)** | POSTs `/api/jobs/:id/retry` (allowed for **failed** or **partial**), reloads list, then loads the (new) job into detail. Errors shown via **toast**. | “Retry job” button in detail panel. |
| **submitJob()** | Validates `rows` (10k–1B), POSTs `/api/jobs` with `{ rows: num }`, reloads list; **does not** open the new job in detail. On 401, clears token and redirects to login. Validation and API errors shown via **toast** only. | Form submit; button disabled by `submitting`. |
| **displayProgress(job)** | Returns a percentage: for completed/partial, row-based (rows_processed/requested_rows) capped at 100; otherwise progress_percent. | Job list (progress bar and text) and detail panel (progress %). |
| **statusLabel(status)** / **phaseLabel(status)** | Human-readable status and phase labels. | List and detail. |
| **statusBadgeColor(status)** | Badge color for status (e.g. success/error/neutral). | List and detail (UBadge). |
| **formatKbytes(bytes)** | Formats bytes as KB (or “—”). | List and detail (Memory column / memory in grid). |
| **logout()** | Sets `token.value = null`, navigates to `/login`. | “Logout” button in header. |

**Watch:** When the list of job IDs in `jobs.value.data` changes, it unsubscribes from the previous real-time subscription and calls `useJobProgress(ids, applyProgress)` so progress events for the current list update the UI. When a job completes or is partial, `applyProgress` calls `loadJobs()` so the list (including Memory) is refetched.

**Lifecycle:** `onMounted` runs `loadUser()` then `loadJobs()`. `onUnmounted` unsubscribes from progress and stops polling.

**Progress:** The **list** is a table with a progress bar column (`UProgress`, size xs) per job and Memory in KB. The **detail panel** shows Status, Progress %, Execution time, Memory (KB); no progress bars. Polling runs while the selected job is in progress so the detail stays in sync.

**Template (what’s used where):** Header uses `user`, `logout`. New-job section uses `rows`, `submitting`, `submitJob` (errors via toast). **Your jobs** table uses `jobs.data`, `statusFilter`, `sortBy`, `sortOrder`, `loadJobDetail`, `statusLabel`, `phaseLabel`, `statusBadgeColor`, `displayProgress`, `formatKbytes`, progress bar column, and **pagination** (`setJobsPage`, “Showing X–Y of Z jobs”, Previous/Next). Detail panel uses `selectedJob`, grid (status, progress %, execution time, memory), `retryJob` (failed or partial), `retrying`, `JobTemperatureChart` with `chartPaginatedResults.rows`, and city table with `paginatedCityResults.rows` and pagination.

---

### 2. `frontend/composables/useApi.ts`

**Approach:** One place for “call the backend with the right base URL and auth.” Token comes from the same cookie the middleware checks.

**What it does:** Reads `useRuntimeConfig()` for API base URL and `useCookie('auth_token')` for the token. Returns a **fetch** function that: builds the URL from base + path, sets JSON headers, adds `Authorization: Bearer <token>` when present, and allows `body` to be a plain object (it stringifies it). Also returns **token** (so callers can clear it on logout/401) and **apiBase**.

**Used in:** Dashboard, admin page, and admin middleware (to load user and check `is_admin`). Every `apiFetch('/api/...')` and `token.value = null` comes from here.

---

### 3. `frontend/composables/useJobProgress.ts`

**Approach:** Subscribe to Reverb (default) or Pusher channels per job ID; on each `progress` event, call a callback so the UI can update without polling.

**What it does:** `getBroadcastClient()` creates a Pusher-protocol client: **Reverb** by default (reverbKey, reverbHost, reverbPort, reverbScheme from Nuxt config) or **Pusher** when `usePusher` is true and pusherKey is set. **useJobProgress(jobIds, onProgress)** subscribes to **private** channel `private-measurement_job.{id}` for each id (auth via backend `/broadcasting/auth`), binds the `progress` event, and calls `onProgress` with the payload. Returns an unsubscribe function that unbinds and unsubscribes (used when job list changes or component unmounts).

**Used in:** Dashboard only: the watch on job IDs calls `useJobProgress(ids, applyProgress)` so progress events update the list and the selected job detail.

---

### 4. `frontend/middleware/auth.ts`

**Approach:** Run before pages that require login; redirect if there’s no token.

**What it does:** Reads `useCookie('auth_token')`. If `token.value` is falsy, returns `navigateTo('/login')`. Otherwise the request continues.

**Used in:** `definePageMeta({ middleware: 'auth' })` on dashboard (and any other protected page).

---

### 5. `frontend/middleware/admin.ts`

**Approach:** After auth, ensure the user is an admin by calling the API.

**What it does:** If no token, redirect to login. Otherwise calls `apiFetch('/api/user')` and checks `res?.user?.is_admin`; if not admin, redirects to `/dashboard`.

**Used in:** `definePageMeta({ middleware: ['auth', 'admin'] })` on the admin page.

---

## Backend (short)

- **Auth:** `AuthController` (register, login) creates a Sanctum token; protected routes use `auth:sanctum`. Token is sent from frontend in `Authorization` and stored in cookie.
- **Jobs API:** `JobController` – index (list with filter/sort), store (create job + dispatch GenerateMeasurementsJob), show (one job), retry (new job, same rows).
- **Job pipeline:** GenerateMeasurementsJob → creates file, chunks, dispatches ProcessChunkJobs in a batch; when batch done, AggregateResultsJob runs. AggregateResultsJob sets job `memory_used_bytes` to the sum of chunk processing memory and marks job completed or partial. Each progress step can call `broadcast(MeasurementJobProgress::fromJob($job))`.
- **Real-time:** Event `MeasurementJobProgress` broadcasts on **private** channel `measurement_job.{id}` with event name `progress` and payload (id, status, progress_percent, rows_processed, memory_used_bytes, etc.). Backend authorizes subscription in `routes/channels.php` (job owner only). Frontend subscribes to `private-measurement_job.{id}` with Bearer token; useJobProgress passes payload to `applyProgress`. When status is completed or partial, the frontend refetches the job list so the Memory column is correct.

For full pipeline and queue details, see **WORKFLOW.md** and **TEACHING_GUIDE.md**.

---

## How this document helps the user

- **Approach and why:** First section and each file’s “Approach” line.
- **What every function does:** Tables under each frontend file.
- **What is used in the front:** “Used in” and “Template (what’s used where)” in the dashboard section; “Used in” for composables and middleware.

You can teach from this file while sharing your screen and scrolling through the same code.
