# Frontend (Nuxt.js + Vue.js + Tailwind CSS)

## Setup

```bash
npm install
```

## Environment

Create `.env` (optional) to override the API base URL:

```
NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000
```

Default is `http://127.0.0.1:8000` (Laravel backend).

## Run

```bash
npm run dev
```

Open http://localhost:3000

## Pages

- `/` – Home (links to Login / Register)
- `/login` – Sign in
- `/register` – Create account
- `/dashboard` – Protected; requires login (auth token in cookie)

Ensure the Laravel backend is running (`php artisan serve`) and CORS is configured so the API accepts requests from this origin.
