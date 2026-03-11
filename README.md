# Measurements Dashboard – Full-Stack Project

A full-stack web application where users submit **measurement jobs**: the backend generates a large file of `city;temperature` data, processes it in chunks, and computes **min**, **max**, and **average** temperature per city. Users see their jobs and results on a dashboard, with **real-time progress** (Reverb by default, or Pusher with credentials) and an **admin** area.

This README gives you an overview, setup, and run instructions. For **how the system works** (user flow, job pipeline, queues, real-time), see **[guide.md](./guide.md)** and **[CODE_WALKTHROUGH.md](./CODE_WALKTHROUGH.md)**.

---

## Clone and run on a new computer

If you just cloned this repo (e.g. from Git), follow these steps **once** to install all modules and libraries, then run the app.

### Prerequisites (install these first)

- **PHP 8.2+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- **Composer** – [getcomposer.org](https://getcomposer.org)
- **Node.js 18+** and **npm** – [nodejs.org](https://nodejs.org)
- **MySQL** (or MariaDB) – running and reachable

### 1. Clone the repository

```bash
git clone <repository-url>
cd <project-folder-name>
```

Use the actual repo URL and folder name (e.g. `measurement` or `measurements-dashboard`).

### 2. Backend: PHP dependencies and database

```bash
cd backend
cp .env.example .env
```

Edit **`.env`** and set your database and app URL:

- `DB_DATABASE=` your database name  
- `DB_USERNAME=` your MySQL user  
- `DB_PASSWORD=` your MySQL password  
- `APP_URL=http://127.0.0.1:8000`

Then run:

```bash
php artisan key:generate
composer install
php artisan migrate
php artisan reverb:install --no-interaction
cd ..
```

- **`composer install`** – installs all PHP libraries (Laravel, Sanctum, Reverb, Pusher SDK, etc.) into `backend/vendor/`.
- **`php artisan migrate`** – creates the database tables.
- **`php artisan reverb:install --no-interaction`** – adds `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` to `.env` for real-time progress.

### 3. Frontend: Node modules

```bash
cd frontend
npm install
cd ..
```

- **`npm install`** – installs all frontend dependencies (Nuxt 4, Vue, Nuxt UI, Tailwind, Chart.js, Pusher JS, etc.) into `frontend/node_modules/`.

Create **`frontend/.env`** (copy from `frontend/.env.example`):

- `NUXT_PUBLIC_REVERB_APP_KEY=` — **must match** `REVERB_APP_KEY` in `backend/.env` (so real-time progress works). Run `php artisan reverb:install --no-interaction` in `backend/` first if you don’t have Reverb keys yet.
- Optionally: `NUXT_PUBLIC_REVERB_HOST=127.0.0.1`, `NUXT_PUBLIC_REVERB_PORT=8080`, `NUXT_PUBLIC_REVERB_SCHEME=http`.
- Optional: `NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000` (default is already this).

### 4. Root: runner script (optional but recommended)

From the **project root** (the folder that contains `backend/` and `frontend/`):

```bash
npm install
```

This installs **concurrently** so you can start backend, queue workers, and frontend with one command.

### 5. Run the project

From the **project root**:

```bash
npm start
```

This starts:

- Laravel API at **http://127.0.0.1:8000**
- **Reverb** WebSocket server (real-time job progress)
- Queue workers (generation + default)
- Nuxt frontend at **http://localhost:3000**

Open **http://localhost:3000** in the browser → Register → Log in → submit a job and use the dashboard. Real-time progress works when Reverb is running and `NUXT_PUBLIC_REVERB_APP_KEY` matches the backend.

### Summary: what gets installed where

| Location   | Command           | What it installs |
|-----------|-------------------|-------------------|
| `backend/`  | `composer install` | PHP libraries (Laravel, Sanctum, Pusher, etc.) → `backend/vendor/` |
| `frontend/` | `npm install`      | Node modules (Nuxt, Vue, Tailwind, Chart.js, Pusher JS, etc.) → `frontend/node_modules/` |
| project root | `npm install`    | `concurrently` → `node_modules/` (for `npm start`) |

**Filament (backend admin):** See **[backend/FILAMENT_SETUP.md](./backend/FILAMENT_SETUP.md)** for enabling PHP `intl`/`zip` and installing the Filament panel. The app also has a custom **Admin** page in the frontend (stats, all jobs, users) for users with `is_admin`.

**Do not commit** `backend/vendor/`, `frontend/node_modules/`, or root `node_modules/`. They are recreated by the commands above after clone.

---

## What This Project Does

- **Users** register, log in, and submit jobs with a **number of rows** (e.g. 10,000 to 1,000,000,000).
- **Backend** generates a text file (`city;temperature` per line), splits it into chunks, processes each chunk (min/max/sum/count per city), then **aggregates** results into one table per job.
- **Frontend** shows a job list **table** (filter/sort, status, phase, progress bar, execution time, **memory in KB**, progress text, date) with server-side pagination; **job detail** (status, progress %, execution time, memory; **temperature chart** and table); **retry** for failed or partial jobs; and (for admins) an **Admin** dashboard (stats, all jobs, all users). Submitting a job does not auto-open the detail panel; the list refetches when a job completes so the Memory column updates without opening the job.

---

## Tech Stack

| Layer    | Technology |
|----------|------------|
| Backend  | **Laravel 12** (PHP), MySQL, Sanctum (API auth), **Filament v5** (admin panel at `/admin`), Queues (database driver), Pusher (real-time; add credentials to enable) |
| Frontend | **Nuxt 4** (Vue 3), **Nuxt UI**, Tailwind CSS, Chart.js, Pusher JS (real-time; add key to enable) |
| Real-time | **Laravel Reverb** (default; WebSocket server); optional **Pusher** (add credentials in backend + frontend `.env` for live job progress). Polling when a job is in progress keeps the list and detail in sync. |

---

## Prerequisites

- **PHP 8.2+** (with extensions: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json)
- **Composer**
- **Node.js 18+** and **npm**
- **MySQL** (or another DB; adjust `.env`)

---

## Quick Start

### One-command start (after first-time setup)

From the **project root** (the folder that contains `backend/` and `frontend/`):

```bash
npm install   # only once at root, if you haven’t already
npm start
```

This runs together: Laravel server, generation queue worker, default queue worker, and Nuxt frontend. Backend: **http://127.0.0.1:8000**. Frontend: **http://localhost:3000**.

**First time on a new machine?** Do the full **[Clone and run on a new computer](#clone-and-run-on-a-new-computer)** steps above, then use `npm start` from the project root.

---

### First-time setup (reference; same as clone section above)

#### 1. Backend

```bash
cd backend
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
composer install
php artisan migrate
```

#### 2. Frontend

```bash
cd frontend
npm install
# Optional: create .env with NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000
```

#### 3. Root (for one-command start)

```bash
cd ..   # back to project root
npm install
npm start
```

- **generation** worker – creates the big measurements file and splits into chunks.
- **default** worker – processes chunks and runs aggregation.

### 4. Use the app

1. Open http://localhost:3000 → Register → Log in.
2. On the **Dashboard**, enter a number of rows (e.g. **50000**) and click **Submit job** (the new job appears in the list; detail panel does not open automatically).
3. Click a job row in the table to open its detail (status, progress %, execution time, memory; when completed, **temperature chart** and results table).
4. Use **filter** (status) and **sort** (date, status, progress, rows). For **failed** or **partial** jobs, use **Retry job**.

---

## Real-time progress (Reverb / Pusher)

**Default:** Laravel **Reverb** (self-hosted WebSocket). After `php artisan reverb:install`, set `NUXT_PUBLIC_REVERB_APP_KEY` in `frontend/.env` to match `REVERB_APP_KEY` in `backend/.env`. The job list subscribes to progress for all visible job IDs; when a job completes or is partial, the list is refetched so the **Memory** column shows the correct value. **Polling** (e.g. 1.5s) runs while the selected job is in progress so the detail panel stays in sync.

**Optional Pusher:** To use Pusher instead of Reverb: (1) Create a Pusher app at [pusher.com](https://pusher.com). (2) **Backend** `.env`: set `BROADCAST_CONNECTION=pusher`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`. (3) **Frontend** `.env`: set `NUXT_PUBLIC_USE_PUSHER=true`, `NUXT_PUBLIC_PUSHER_KEY`, `NUXT_PUBLIC_PUSHER_CLUSTER`. Restart backend and frontend.

---

## Optional: Admin dashboard (frontend + Filament)

**Frontend Admin page (custom):**  
1. Make a user admin: `php artisan user:admin your@email.com` (from `backend/`).  
2. Log in as that user; you will see **Admin** in the header.  
3. Open **Admin** to see **Stats** (total jobs, users, jobs by status), **All jobs**, and **Users**.

**Filament admin panel (Laravel):**  
- Install and configure per **[backend/FILAMENT_SETUP.md](./backend/FILAMENT_SETUP.md)** (requires PHP `intl` and `zip`).  
- Panel URL: **http://127.0.0.1:8000/admin** (separate from the frontend Admin page).

---

## Project structure

```
<project-root>/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Console/Commands/   # generate:measurements, user:admin
│   │   ├── Events/             # MeasurementJobProgress (broadcast)
│   │   ├── Http/Controllers/Api/  # Auth, Job, Admin
│   │   ├── Jobs/                # GenerateMeasurements, ProcessChunk, AggregateResults
│   │   └── Models/
│   ├── config/
│   ├── database/migrations/
│   ├── routes/api.php
│   └── .env
├── frontend/                # Nuxt 4 app (Nuxt UI, Tailwind)
│   ├── app/                 # app config (e.g. router.options.ts)
│   ├── assets/css/          # main.css (Tailwind + Nuxt UI)
│   ├── components/          # AppPageLayout, AppHeader, JobTemperatureChart
│   ├── composables/         # useApi, useJobProgress
│   ├── middleware/          # auth, admin
│   ├── pages/               # index, login, register, dashboard, admin/index
│   └── .env
├── README.md                # This file
├── guide.md                 # How the system works (stack, auth, jobs, real-time, dashboard)
├── CODE_WALKTHROUGH.md      # Code walkthrough and frontend usage
└── APPROACH_AND_REASONS.md  # Design decisions and reasons
```

---

## Main features (summary)

| Feature | Description |
|--------|-------------|
| **Auth** | Register, login, logout (Sanctum; token in cookie). |
| **Submit job** | POST rows (10k–1B); backend queues generation and processing. |
| **Job list** | **Table** with columns: #, Rows, Status, Phase, Progress bar, Execution time, Memory (KB), Progress text, Date. Filter by status, sort by date/status/progress/rows; **server-side pagination** (15 per page). List **refetches** when a job completes or is partial so Memory updates without opening the job. |
| **Job detail** | Grid: Status, Progress %, Execution time, Memory (processing, in KB); error message; **Retry** for failed or partial. Temperature **chart** and **temperature-by-city table** with **client-side pagination** (20 per page). **Polling** when the selected job is in progress. |
| **Notifications** | **Toasts** (Nuxt UI) for errors only (validation, API failures); no inline error paragraphs. |
| **Real-time** | Reverb (default) or optional Pusher; list subscribes to progress for visible job IDs; list refetch on completed/partial so Memory is correct. Polling when selected job is in progress. |
| **Retry** | For **failed** or **partial** jobs: create new job with same row count. |
| **Admin** | Stats, all jobs, all users (only for users with `is_admin`). |

---

## Where to learn more

- **Setup / run:** This README and `backend/README.md`, `frontend/README.md`.
- **How it works (workflow, queues, real-time):** **[guide.md](./guide.md)** – stack, auth, jobs, real-time, dashboard.
- **Code walkthrough (approach, every function, what’s used in the front):** **[CODE_WALKTHROUGH.md](./CODE_WALKTHROUGH.md)** – full code explanation and frontend usage.
- **Design decisions:** **[APPROACH_AND_REASONS.md](./APPROACH_AND_REASONS.md)** – why each approach was chosen. – sessions, “where in the code,” and a “How to make a walkthrough video” section.

---

## License

MIT (or as per your project).
