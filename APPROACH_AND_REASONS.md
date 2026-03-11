# Project approach and reasons

| Area | Approach | Reason |
|------|----------|--------|
| **Stack** | Laravel API + Nuxt 4 SPA (Nuxt UI) | Clear backend/frontend split; API reusable; good tooling on both sides; Nuxt 4 with Nuxt UI for components and toasts. |
| **Auth** | Sanctum token in cookie, Bearer header on API calls | Stateless API; simple SPA auth; one token for middleware and API. |
| **Heavy work** | Queue jobs instead of doing work in the HTTP request | Avoid timeouts and memory limits; API stays fast; jobs can retry. |
| **Queues** | Two queues: `generation` and `default` | Long generation doesn’t block chunk processing; can scale workers per queue. |
| **Processing** | Chunk files → many ProcessChunkJobs → one AggregateResultsJob | Each chunk fits in memory; parallel work; single final aggregation step. |
| **Dashboard UI** | One page: list table + detail panel (no `/job/:id` route) | Simpler state; one place to wire list and detail; easy real-time updates. |
| **Submit job** | Do not auto-open the new job in the detail panel | User can keep viewing the list; detail opens only when user clicks a row. |
| **List memory** | Refetch job list when a job completes or is partial | Memory column gets correct value from server without opening the job. |
| **Retry** | Allowed for **failed** and **partial** jobs | Partial = only some rows processed; user can retry to get full run. |
| **Memory display** | Show processing memory in KB (list and detail) | Single metric; `memory_processing_bytes ?? memory_used_bytes` so list updates from broadcast/refetch. |
| **Real-time** | Reverb (default) or Pusher: backend broadcasts, frontend subscribes | Live progress; list subscribes to visible job IDs; refetch on completion for Memory. |
| **Frontend logic** | Composables: `useApi`, `useJobProgress` | One place for API client and token; reusable real-time logic; easy cleanup. |
| **Access control** | Route middleware: `auth`, `admin` | Protected routes are explicit; redirect in one place; no repeated checks in pages. |
| **Admin (backend)** | Filament v5 panel at `/admin` | Optional Laravel admin UI; see `backend/FILAMENT_SETUP.md`. App also has a custom frontend Admin page for `is_admin` users. |
| **Your jobs list** | Server-side pagination (15 per page) | Backend returns one page; “Showing X–Y of Z jobs”, Previous/Next; filter/sort resets to page 1. |
| **Dashboard city table** | Client-side pagination (20 per page) | Keeps UI responsive when a job has many cities; Previous/Next, “Page X of Y”, “Showing X–Y of Z cities”. |
| **Dashboard chart** | Client-side pagination (20 per page) | Same pattern for the temperature-by-city chart. |
| **Progress bar** | One progress bar column in the list per job; detail shows numeric % only; polling when selected job in progress | List shows bar per row; detail stays in sync via polling and real-time. |
| **List row sync** | `loadJobDetail` updates the job in `jobs.value.data`; `applyProgress` + refetch on completed/partial | Selected job’s row stays in sync; list refetch ensures Memory is correct when a job finishes. |
| **Notifications** | Toasts (Nuxt UI) for errors only | Validation and API failures shown via `useToast()`; no inline error paragraphs. |
