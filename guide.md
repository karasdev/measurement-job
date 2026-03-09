## 1. Introduction and project overview

This project is a measurement processing system. Users can submit jobs to generate large files of weather measurements, process them in chunks, and see results with live progress. The design follows a clear table of decisions: Laravel API plus Nuxt SPA, Sanctum auth, queue-based heavy work, chunked processing, one dashboard page, optional Pusher for real time, composables for API and progress, and route middleware for access control. In the video we go through each of these and point to the exact functions and files.

---

## 2. Stack and where things live

**Role:** Define the technological split between backend and frontend.

**Position:**  
- Backend: Laravel in the `backend` folder — API, jobs, models, events; Filament v5 admin panel at `/admin`.  
- Frontend: Nuxt 4 in the `frontend` folder — pages, composables, middleware, components; Nuxt UI for layout and toasts.

**How it works:** The frontend never renders Laravel Blade; it only talks to the Laravel API over HTTP. All API calls use a base URL from Nuxt config and go to routes under `/api`. This keeps backend and frontend separate and lets each use its own tooling.

---

## 3. Authentication — role, position, and flow

**Role:** Identify the user and protect API routes and pages.

**Position:**  
- Backend: `backend/app/Http/Controllers/Api/AuthController.php`.  
  - `register`: validates name, email, password; creates user; creates a Sanctum token; returns user and token.  
  - `login`: validates email and password; finds user; checks password with Hash; creates token; returns user and token.  
  - `logout`: deletes the current access token.  
  - `user`: returns the authenticated user (used by frontend for header and admin check).  
- Routes: in `backend/routes/api.php`, register and login are public; logout, user, and all job and admin routes sit inside the `auth:sanctum` middleware group.  
- Frontend: the token is stored in a cookie named `auth_token`. The frontend sends it as `Authorization: Bearer <token>` on every API request.

**How it works:** On login or register, the API returns a Sanctum token; the frontend stores it in the cookie. Every protected API call includes that header; Laravel’s `auth:sanctum` middleware validates the token. The API stays stateless; the SPA has one place (the cookie and useApi) to manage auth.

---

## 4. Heavy work — why it’s in jobs, not HTTP

**Role:** Do long-running or memory-heavy work without blocking the web request or hitting timeouts.

**Position:**  
- Heavy work is never in controllers. It lives in queue job classes:  
  - `backend/app/Jobs/GenerateMeasurementsJob.php`  
  - `backend/app/Jobs/ProcessChunkJob.php`  
  - `backend/app/Jobs/AggregateResultsJob.php`  
- The controller that starts everything is `backend/app/Http/Controllers/Api/JobController.php`, method `store`. It creates a `MeasurementJob` record and dispatches `GenerateMeasurementsJob`; it does not wait for generation to finish.

**How it works:** When a user submits a job, the API creates the job row and pushes one job onto the queue, then returns immediately with a job ID. Workers run the jobs in the background. So the HTTP request is fast, and we avoid timeouts and memory limits; failed jobs can retry with backoff.

---

## 5. Queues — two queues and their roles

**Role:** Separate long “generation” work from “default” work so that slow generation doesn’t block other processing.

**Position:**  
- Queue configuration: `backend/config/queue.php` (and env) define connections and queues.  
- In code: `GenerateMeasurementsJob` in its constructor sets `$this->onQueue('generation')`.  
- The batch of `ProcessChunkJob` and the `AggregateResultsJob` are dispatched with `->onQueue('default')` in `GenerateMeasurementsJob.php`.

**How it works:** Generation runs on the `generation` queue; chunk processing and aggregation run on `default`. You can run different workers for each queue (e.g. one worker for generation, several for default). Long file generation doesn’t block chunk or aggregate jobs.

---

## 6. Processing pipeline — chunks, many jobs, one aggregate

**Role:** Process large data in a memory-safe, parallel way and produce one final result set.

**Position:**  
- **GenerateMeasurementsJob** (file: `backend/app/Jobs/GenerateMeasurementsJob.php`):  
  - `handle()`: sets status to “generating”, creates a directory, runs the `generate:measurements` Artisan command to write the big file, records a metric, then splits the file into chunk files (e.g. 2 million lines per chunk). It then builds an array of `ProcessChunkJob` instances (one per chunk) and dispatches them in a batch. The batch’s `then` callback dispatches `AggregateResultsJob`; the `catch` callback marks the job failed and broadcasts progress.  
  - Uses the `generation` queue.  
- **ProcessChunkJob** (file: `backend/app/Jobs/ProcessChunkJob.php`):  
  - `handle()`: opens one chunk file, reads line by line, parses city and temperature, aggregates per city (min, max, sum, count), inserts into `chunk_temperature_results`, records a metric, increments the measurement job’s `rows_processed`, recomputes progress percent and broadcasts it, then deletes the chunk file.  
  - Runs on the `default` queue as part of the batch.  
- **AggregateResultsJob** (file: `backend/app/Jobs/AggregateResultsJob.php`):  
  - `handle()`: reads all chunk results for the job, groups by city with MIN, MAX, SUM, COUNT, computes average, inserts into `temperature_results`, marks the job completed, broadcasts progress, and deletes chunk results.  
  - Runs on the `default` queue after the batch completes.

**How it works:** One big file is split into many chunk files. Many ProcessChunkJobs run in parallel (or as workers are free); each chunk fits in memory. When all chunk jobs finish, a single AggregateResultsJob merges everything into one table. So: chunks → many jobs → one aggregate. Memory-safe and parallel.

---

## 7. Real-time progress — event and frontend subscription

**Role:** Push progress updates to the browser so the UI updates in real time (no polling).

**Position:**  
- Backend: `backend/app/Events/MeasurementJobProgress.php`.  
  - `fromJob(MeasurementJob $job)`: static factory that builds the event from the job model (id, status, progress_percent, rows_processed, etc.).  
  - `broadcastOn()`: returns the channel `measurement_job.{id}`.  
  - `broadcastAs()`: event name is `progress`.  
  - `broadcastWith()`: returns the payload the frontend expects (id, status, progress_percent, rows_processed, etc.).  
- Every place that updates job status or progress (GenerateMeasurementsJob, ProcessChunkJob, AggregateResultsJob, and their `failed` methods) calls `broadcast(MeasurementJobProgress::fromJob($job))`.
- Backend uses **Laravel Reverb** by default (`BROADCAST_CONNECTION=reverb`); **Pusher** is optional (set `BROADCAST_CONNECTION=pusher` and PUSHER_* credentials to use it instead).
- Frontend: `frontend/composables/useJobProgress.ts`.  
  - `getBroadcastClient()`: creates a Pusher-protocol client: **Reverb** by default (reverbKey, reverbHost, reverbPort, reverbScheme from Nuxt config), or **Pusher** when `usePusher` is true and pusherKey is set.  
  - `useJobProgress(jobIds, onProgress)`: for each job ID, subscribes to the channel `measurement_job.{id}`, binds the `progress` event, and calls `onProgress` with the payload. Returns an unsubscribe function that unbinds and unsubscribes.

**How it works:** Backend broadcasts to a per-job channel with event name “progress”. Frontend subscribes via Reverb (main) or Pusher (optional) using the same Pusher protocol; when an event arrives, it updates the list and detail panel. No polling; if neither Reverb nor Pusher is configured, progress is shown after refresh or when re-opening a job.

---

## 8. Dashboard UI — one page, list and detail panel

**Role:** Single place to see jobs, submit a job, and see one job’s details and chart.

**Position:**  
- `frontend/pages/dashboard.vue`: one page, no separate route for `/job/:id`.  
- Page meta: `definePageMeta({ middleware: 'auth' })` so only logged-in users see it.  
- State: `user`, `jobs` (paginated list with `page`, `total`, `from`, `to`), `selectedJob`, `rows`, `submitting`, filters and sort, `retrying`, `jobsPage`/`jobsPerPage` (15) for **Your jobs** list pagination, `cityTablePage`/`cityTablePerPage` (20), `chartPage`/`chartPerPage` (20), `smoothedProgressPercent` for the progress bar.  
- Key functions:  
  - `loadUser()`: fetches `/api/user`, sets `user`; on failure clears token and redirects to login.  
  - `loadJobs()`: builds query from filters/sort **and page/per_page**, calls `/api/jobs`, sets `jobs`; used for “Your jobs” list.  
  - `loadJobDetail(id)`: fetches `/api/jobs/:id`, sets `selectedJob`, and **updates the same job in `jobs.value.data`** so the list row shows the same progress as the detail bar.  
  - `submitJob()`: validates rows (e.g. 10k–1B), POSTs `/api/jobs`, resets to page 1, reloads list and opens the new job; errors via **toast**.  
  - `retryJob(id)`: POSTs retry, resets to page 1, reloads list, loads the new job; errors via toast.  
  - `applyProgress(payload)`: callback for real-time; updates the matching job in the list and in `selectedJob`; when status is completed, calls `loadJobDetail`.  
  - `setJobsPage(p)`, `onJobsFilterOrSortChange()`: “Your jobs” pagination and reset-to-page-1 when filter/sort changes.  
  - Helpers: `displayProgress`, `statusColor`, `formatBytes`, `logout`; `paginatedCityResults` and `setCityTablePage` for city table; `chartPaginatedResults` and `setChartPage` for chart.  
- **Progress bar:** When status is **generating** or **aggregating**, an **indeterminate** bar (moving, like copy-paste) is shown; when **processing**, a **determinate** bar with smoothed percentage. Updates come only from **real-time** (Reverb or optional Pusher); there is no polling.  
- **Notifications:** Only **toasts** for validation and API errors.  
- A watch on job IDs calls `useJobProgress(ids, applyProgress)`; a watch on selected job id/status sets the smoothed progress and (when processing) starts the smooth progress animation.

**How it works:** One page keeps state simple; the list and detail panel are always in sync. Real-time progress is wired in one place via the `applyProgress` callback, so both the list and the detail update without refresh.

---

## 9. Frontend logic — composables for API and job progress

**Role:** Centralize API calls (base URL + auth) and real-time progress subscription so every page uses the same behavior.

**Position:**  
- `frontend/composables/useApi.ts`:  
  - Reads `useRuntimeConfig()` for API base URL and `useCookie('auth_token')` for the token.  
  - Returns a `fetch` function that builds the URL, sets JSON headers, adds `Authorization: Bearer <token>` when present, and allows `body` to be a plain object (stringifies it).  
  - Also returns `token` (so callers can clear it on logout or 401) and `apiBase`.  
- `frontend/composables/useJobProgress.ts`:  
  - Described above: subscribe to Reverb (or optional Pusher) per job ID, call callback on `progress`, return unsubscribe.

**How it works:** Dashboard and admin (and any future page) use `useApi()` for all requests and one place to clear the token. Progress is always subscribed via `useJobProgress` with a callback; reuse and cleanup are in one place.

---

## 10. Access control — route middleware

**Role:** Enforce “must be logged in” and “must be admin” before a page is rendered.

**Position:**  
- `frontend/middleware/auth.ts`: reads `useCookie('auth_token')`; if no token, returns `navigateTo('/login')`.  
- `frontend/middleware/admin.ts`: if no token, redirect to login; otherwise calls `apiFetch('/api/user')` and checks `res?.user?.is_admin`; if not admin, redirects to `/dashboard`.  
- Usage: dashboard uses `middleware: 'auth'`; admin page uses `middleware: ['auth', 'admin']`.

**How it works:** Middleware runs before the route; protection is at the router level, so we don’t repeat checks inside each page. Clear and centralized.

---

## 11. Jobs API — controller methods and their roles

**Position:** `backend/app/Http/Controllers/Api/JobController.php`.

- **store**: Validates “rows” (with min/max from config), creates a `MeasurementJob` with status “pending”, dispatches `GenerateMeasurementsJob`, returns job ID. Does not run the heavy work itself.  
- **index**: Lists jobs for the current user with optional status filter and sort (created_at, status, progress_percent, etc.), paginated.  
- **show**: Returns one job by ID (for current user) with metrics and temperature results.  
- **retry**: Finds the failed job, creates a new MeasurementJob with the same requested_rows, dispatches `GenerateMeasurementsJob` again, returns the new job ID.

These are the only HTTP entry points for jobs; all heavy work is in the job classes.

---

## 12. Artisan command — file generation only

**Role:** Generate the raw measurements file that the pipeline then chunks and processes.

**Position:**  
- `backend/app/Console/Commands/GenerateMeasurements.php` (and the root `GenerateMeasurements.php` if it’s the same or a copy).  
- Signature: `generate:measurements {path} {count} {batch-size=500}`.  
- `handle()`: clears or creates the file, then in a loop picks a random station, generates a temperature (with `generateTemperature` using a normal distribution), writes “station\ttemperature” to a buffer, and flushes to file every `batch-size` lines.  
- `generateTemperature`, `randomGaussian`: used to produce realistic temperature values.

**How it works:** This command is invoked by `GenerateMeasurementsJob`, not by HTTP. It only writes the file; splitting and processing are done by the job pipeline.

---

## 13. Closing summary

To recap: the **stack** is Laravel API plus Nuxt SPA. **Auth** is Sanctum token in a cookie and Bearer header, with AuthController and auth middleware. **Heavy work** lives in three jobs: GenerateMeasurementsJob (file + chunks + baWtch), ProcessChunkJob (per-chunk aggregation), AggregateResultsJob (final merge). **Queues** are “generation” and “default”. **Real-time** is the MeasurementJobProgress event and useJobProgress subscribing to Pusher. **Dashboard** is one page with list and detail and applyProgress for live updates. **Composables** are useApi and useJobProgress. **Access control** is auth and admin middleware. Every function’s role and position is chosen so the system stays scalable, clear, and easy to extend.