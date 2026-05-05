# Measurements Dashboard

A professional full-stack dashboard for generating and processing large city temperature measurement jobs. Users submit a workload, the backend generates measurement rows, processes the file in queued chunks, aggregates min/max/average temperatures per city, and displays results in a polished operational UI with progress, runtime, memory, chart, and table views.

For a deeper implementation walkthrough, see **[guide.md](./guide.md)** and **[CODE_WALKTHROUGH.md](./CODE_WALKTHROUGH.md)**.

---

## Description

Measurements Dashboard is designed to demonstrate a production-style data processing workflow with a Laravel API and a Nuxt frontend. Instead of doing heavy work during an HTTP request, the backend dispatches queue jobs that generate a measurements file, split it into chunks, process each chunk, and aggregate the final city-level statistics.

The dashboard gives users a clean view of the whole pipeline:

- Submit a measurement job by choosing the number of rows.
- Track total jobs, completed jobs, processed rows, and average progress.
- Filter and sort jobs by status, date, progress, and requested rows.
- Inspect each job’s status, progress, runtime, memory usage, and city count.
- Review aggregated city results in a temperature chart and paginated results table.
- Receive live progress updates through Laravel Reverb, with polling as a fallback.

---

## Features

- **Authentication:** Register, log in, log out, and access protected dashboard routes.
- **Queued processing:** Separate generation and default queues for heavy workloads.
- **Chunked aggregation:** Memory-safe processing for large measurement files.
- **Real-time progress:** Laravel Reverb broadcasts job progress to the Nuxt frontend.
- **Operational dashboard:** Summary cards, job table, status badges, runtime, memory, and row progress.
- **Job details:** Chart.js visualization plus paginated city-level min/max/avg/count table.
- **Retry support:** Retry failed or partial jobs.
- **Admin support:** Frontend admin page and optional Filament admin panel.
- **Dockerized setup:** Run the API, frontend, MySQL, Reverb, and workers with Docker Compose.

---

## Interface Overview

The application UI is organized around four main areas:

- **Pipeline summary:** Top-level operational metrics for active jobs, completed jobs, processed rows, and average progress.
- **Job submission:** A compact form for submitting a new measurement workload.
- **Jobs table:** A sortable and filterable job list with status, phase, row progress, runtime, memory, and creation date.
- **Job inspection:** A detail workspace with metric cards, a temperature chart, and a paginated city results table.

---

## Usage

1. Open **http://localhost:3000**.
2. Register a new account or log in.
3. Enter a row count in **Number of rows** and click **Submit job**.
4. Watch the job appear in the jobs table with status, phase, progress, runtime, memory, and creation date.
5. Click a job row to open the detail view.
6. Review the job summary cards, temperature chart, and city results table.
7. Use the filters and sort controls to navigate multiple jobs.

For admin access, make a user an admin from the backend:

```bash
php artisan user:admin your@email.com
```

---

## Tech Stack

| Layer | Technology |
| --- | --- |
| Frontend | **Nuxt 4**, Vue 3, Nuxt UI, Tailwind CSS, Chart.js |
| Backend | **Laravel 12**, PHP 8.2+, Sanctum, Eloquent, Artisan commands |
| Database | MySQL 8.4 or MariaDB |
| Queues | Laravel database queue driver with `generation` and `default` queues |
| Real-time | Laravel Reverb by default, optional Pusher support |
| Admin | Custom Nuxt admin dashboard, optional Filament v5 panel |
| DevOps | Docker, Docker Compose, Composer, npm |

---

## Architecture

```text
Nuxt Dashboard
    |
    | HTTP API + Bearer token
    v
Laravel API  --->  MySQL
    |
    | dispatch jobs
    v
Queue Workers
    |
    | broadcast progress
    v
Laravel Reverb  --->  Browser progress updates
```

The processing pipeline is:

1. The user submits a row count.
2. Laravel creates a `MeasurementJob`.
3. `GenerateMeasurementsJob` creates the measurement file and splits it into chunks.
4. `ProcessChunkJob` aggregates each chunk by city.
5. `AggregateResultsJob` merges chunk results into final city statistics.
6. The dashboard displays completion metrics, chart data, and table results.

---

## Quick Start

### Docker

The recommended way to run the project is Docker Compose:

```bash
docker compose up --build
```

This starts:

- **Frontend:** http://localhost:3000
- **Laravel API:** http://localhost:8000
- **Reverb WebSocket server:** http://localhost:8080
- **MySQL 8.4**
- **Queue workers:** `generation` and `default`

The Compose setup runs database migrations automatically for the backend service and stores MySQL data in the `mysql-data` Docker volume.

Useful Docker commands:

```bash
docker compose down          # stop containers, keep database data
docker compose down -v       # stop containers and remove database data
docker compose logs -f       # follow logs
```

### Local Development

Use this path if you prefer running PHP, Node, and MySQL directly on your machine.

#### Prerequisites

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `intl`, and `zip`
- Composer
- Node.js 18+ and npm
- MySQL or MariaDB

#### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan reverb:install --no-interaction
```

Update `backend/.env` with your database credentials and `APP_URL=http://127.0.0.1:8000`.

#### Frontend

```bash
cd frontend
npm install
cp .env.example .env
```

Set `NUXT_PUBLIC_REVERB_APP_KEY` in `frontend/.env` to match `REVERB_APP_KEY` in `backend/.env`.

#### Run Everything

```bash
cd ..
npm install
npm start
```

This starts Laravel, Reverb, queue workers, and Nuxt together.

---

## Useful Commands

```bash
docker compose up --build       # start full Docker stack
docker compose down             # stop containers
docker compose down -v          # stop containers and remove database volume
docker compose logs -f          # follow all service logs
docker compose exec backend php artisan migrate
docker compose exec frontend npm run build
```

**Do not commit** `backend/vendor/`, `frontend/node_modules/`, root `node_modules/`, `.env` files, or generated build output.

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

## Where to learn more

- **Setup / run:** This README and `backend/README.md`, `frontend/README.md`.
- **How it works (workflow, queues, real-time):** **[guide.md](./guide.md)** – stack, auth, jobs, real-time, dashboard.
- **Code walkthrough (approach, every function, what’s used in the front):** **[CODE_WALKTHROUGH.md](./CODE_WALKTHROUGH.md)** – full code explanation and frontend usage.
- **Design decisions:** **[APPROACH_AND_REASONS.md](./APPROACH_AND_REASONS.md)** – why each approach was chosen.

---

## License

MIT (or as per your project).
