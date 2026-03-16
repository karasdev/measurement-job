# Full Stack Challenge

High-performance temperature processing app with real-time job tracking.


## Tech used (and why)
- Laravel 12 (API + queue jobs): strong backend structure and queue system for heavy processing.
- Nuxt 4 + Vue 3 + Nuxt UI: clean frontend, fast development, ready UI components.
- MySQL: stores users, jobs, metrics, and final results.
- Laravel Reverb (WebSockets): real-time progress updates without manual refresh.
- Filament v5: admin panel for managing users/jobs.
- Docker Compose: run full stack locally with one command.

## What I did (process)
1. Built authentication (register, login, logout) with protected API routes.
2. Added job submission flow where user enters row count and creates a processing job.
3. Implemented background processing with queues:
   - generation queue creates data in chunks
   - default queue processes chunks
   - aggregation step finalizes totals and marks job completed
4. Added real-time dashboard updates using WebSockets (job phase, progress, and completion).
5. Added filtering/sorting and benchmark visibility in the dashboard.
6. Added Filament admin tools, including viewing user jobs.
7. Dockerized the full project .

## Run with Docker (one command)
From project root:

```bash
docker compose up --build
```

Default URLs:
- Frontend: `http://localhost:3000`
- Backend API: `http://127.0.0.1:8000`
- Reverb: `http://127.0.0.1:8080`

Stop:

```bash
docker compose down
```

Remove containers + DB data:

```bash
docker compose down -v
```

## Run without Docker 
Prerequisites:
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL

First-time setup:

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan reverb:install --no-interaction

cd ../frontend
npm install

cd ..
npm install
```

Then start all services:

```bash
npm start
```


**Note:**  
> If you encounter the following Nuxt build error:
>
> ```
> spawn .../node_modules/@esbuild/linux-x64/bin/esbuild EACCES
> ```
>
> It means the `esbuild` binary does not have execute permission.  
> Fix it by running:
>
> ```bash
> chmod +x node_modules/@esbuild/linux-x64/bin/esbuild
> ```

## Benchmark (local machine)
- Windows 11 + WSL
- Intel Core i5-12400F
- 16 GB RAM

| Job ID | Rows | Execution Time (ms) | Memory Used (bytes) |
| --- | ---: | ---: | ---: |
| 4 | 1,000,000,000 | 1,320,219 | 456,123,680 |
| 3 | 100,000,000 | 143,409 | 46,667,704 |
| 2 | 10,000,000 | 9,068 | 4,552,928 |
| 1 | 1,000,000 | 6,907 | 871,232 |

> Actual development time: 4 days (spread across multiple sessions).

