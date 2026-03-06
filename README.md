# Measurements Dashboard – Full-Stack Project

A full-stack web application where users submit **measurement jobs**: the backend generates a large file of `city;temperature` data, processes it in chunks, and computes **min**, **max**, and **average** temperature per city. Users see their jobs and results on a dashboard, with optional **real-time progress** and an **admin** area.

This README gives you an overview, setup, and run instructions. For **how the system works** (user flow, job pipeline, queues, real-time), see **[WORKFLOW.md](./WORKFLOW.md)**.

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
cd ..
```

- **`composer install`** – installs all PHP libraries (Laravel, Sanctum, Pusher SDK, etc.) into `backend/vendor/`.
- **`php artisan migrate`** – creates the database tables.

### 3. Frontend: Node modules

```bash
cd frontend
npm install
cd ..
```

- **`npm install`** – installs all frontend dependencies (Nuxt 4, Vue, Nuxt UI, Tailwind, Chart.js, Pusher JS, etc.) into `frontend/node_modules/`.

Optional: create `frontend/.env` with:

- `NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000`  
(If you skip this, the default in code is already `http://127.0.0.1:8000`.)

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
- Two queue workers (generation + default)
- Nuxt frontend at **http://localhost:3000**

Open **http://localhost:3000** in the browser → Register → Log in → submit a job and use the dashboard.

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
- **Frontend** shows a job list (with filter/sort), job detail (status, progress, execution time, memory, **temperature chart** and table), **retry** for failed jobs, and (for admins) an **Admin** dashboard (stats, all jobs, all users).

---

## Tech Stack

| Layer    | Technology |
|----------|------------|
| Backend  | **Laravel 12** (PHP), MySQL, Sanctum (API auth), **Filament v5** (admin panel at `/admin`), Queues (database driver), Pusher (optional, real-time) |
| Frontend | **Nuxt 4** (Vue 3), **Nuxt UI**, Tailwind CSS, Chart.js, Pusher JS (optional) |
| Real-time | **Pusher Channels** (optional) for live job progress |

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
2. On the **Dashboard**, enter a number of rows (e.g. **50000**) and click **Submit job**.
3. Click a job in the list to see status, progress, and (when completed) the **temperature chart** and results table.
4. Use **filter** (status) and **sort** (date, status, progress, rows). For **failed** jobs, use **Retry job**.

---

## Optional: Real-time progress (Pusher)

1. Create a **Pusher Channels** app at [pusher.com](https://pusher.com) and copy **App ID**, **Key**, **Secret**, **Cluster**.
2. **Backend** `.env`:
   - `BROADCAST_CONNECTION=pusher`
   - `PUSHER_APP_ID=...`  
   - `PUSHER_APP_KEY=...`  
   - `PUSHER_APP_SECRET=...`  
   - `PUSHER_APP_CLUSTER=...` (e.g. `mt1` or `ap2`)
3. **Frontend** `.env`:
   - `NUXT_PUBLIC_PUSHER_KEY=<same as PUSHER_APP_KEY>`
   - `NUXT_PUBLIC_PUSHER_CLUSTER=<same as PUSHER_APP_CLUSTER>`
4. Restart backend and frontend. Job list and detail will update live without refresh.

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
└── WORKFLOW.md              # How the system works (for teaching)
```

---

## Main features (summary)

| Feature | Description |
|--------|-------------|
| **Auth** | Register, login, logout (Sanctum; token in cookie). |
| **Submit job** | POST rows (10k–1B); backend queues generation and processing. |
| **Job list** | Filter by status, sort by date/status/progress/rows; **server-side pagination** (15 per page, Previous/Next, “Showing X–Y of Z jobs”). Changing filter/sort resets to page 1. |
| **Job detail** | Status, **progress bar**: indeterminate (moving) for **generating** / **aggregating**, determinate % for **processing**; execution time, memory, error message; temperature **chart** and **temperature-by-city table** each with **client-side pagination** (20 per page). **Polling** (1s) when a job is in progress so the bar and the list row stay updated without Pusher. |
| **Notifications** | **Toasts** (Nuxt UI) for errors only (validation, API failures); no inline error paragraphs. |
| **Real-time** | Optional Pusher for live progress; without it, **polling** keeps the selected job’s progress bar and list row in sync. |
| **Retry** | For failed jobs: create new job with same row count. |
| **Admin** | Stats, all jobs, all users (only for users with `is_admin`). |

---

## Where to learn more

- **Setup / run:** This README and `backend/README.md`.
- **How it works (workflow, queues, real-time):** **[WORKFLOW.md](./WORKFLOW.md)** – use this to teach the flow to a student.
- **Code walkthrough (approach, every function, what’s used in the front):** **[CODE_WALKTHROUGH.md](./CODE_WALKTHROUGH.md)** – for the student who wants a full code explanation.
- **Teaching order and how to make a video:** **[TEACHING_GUIDE.md](./TEACHING_GUIDE.md)** – sessions, “where in the code,” and a “How to make a walkthrough video” section.

---

## License

MIT (or as per your project).
