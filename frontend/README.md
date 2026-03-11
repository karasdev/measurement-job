# Frontend (Nuxt 4 + Vue 3 + Nuxt UI + Tailwind CSS)

## Setup

```bash
npm install
```

## Environment

Create `.env` (copy from `.env.example` if present). Optional overrides:

```
NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000
```

**Real-time progress (Reverb, default):** Set these so the job list and detail update live:

```
NUXT_PUBLIC_REVERB_APP_KEY=<same as backend REVERB_APP_KEY>
NUXT_PUBLIC_REVERB_HOST=127.0.0.1
NUXT_PUBLIC_REVERB_PORT=8080
NUXT_PUBLIC_REVERB_SCHEME=http
```

Default API base is `http://127.0.0.1:8000` (Laravel backend). Ensure the backend and Reverb are running.

## Run

**Development:**

```bash
npm run dev
```

**Or from project root (backend + workers + Reverb + frontend):**

```bash
npm start
```

Open http://localhost:3000

## Pages

- `/` – Home (links to Login / Register)
- `/login` – Sign in
- `/register` – Create account
- `/dashboard` – Protected (`auth` middleware): job list **table** (filter, sort, progress bar, execution time, memory in KB, etc.), job detail panel (status, progress %, execution time, memory; temperature chart and table), retry for failed/partial jobs.
- `/admin` – Admin only (`auth` + `admin` middleware): stats, all jobs, all users.

Ensure the Laravel backend is running and CORS is configured so the API accepts requests from this origin. For real-time job progress, start Reverb (`php artisan reverb:start`) or use `npm start` from the project root.
