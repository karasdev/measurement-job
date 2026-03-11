<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Measurement Jobs – Queue workers

This app uses two queues so that long file-generation jobs do not block chunk processing:

- **`generation`** – runs `GenerateMeasurementsJob` (creates the big .txt file).
- **`default`** – runs `ProcessChunkJob` and `AggregateResultsJob`.

Run **two** workers (e.g. in separate terminals):

```bash
php artisan queue:work --queue=generation
php artisan queue:work --queue=default
```

That way, when a 1B-row job is generating on `generation`, smaller jobs’ chunk and aggregation work can still run on `default`.

### Retry mechanism

Failed queue jobs are retried automatically:

- **GenerateMeasurementsJob:** 3 tries, backoff 10s → 30s → 60s, timeout 1 hour. After all retries, the measurement job is marked failed with the exception message.
- **ProcessChunkJob:** 3 tries, backoff 10s → 30s → 60s, timeout 10 minutes. If all retries fail, the batch fails and the measurement job is marked "One or more chunk jobs failed".
- **AggregateResultsJob:** 3 tries, backoff 10s → 30s → 60s, timeout 15 minutes. On success it sets the job’s `memory_used_bytes` to the sum of chunk processing memory and marks the job completed or partial. After all retries, the measurement job is marked failed with the exception message.

To change retries or backoff, edit `$tries` and `$backoff` on each job class.

### Real-time job progress (Reverb main, Pusher optional)

Real-time progress uses **Laravel Reverb** by default (self-hosted WebSocket server). No polling.

**Backend**

1. In backend `.env`: ensure `BROADCAST_CONNECTION=reverb` and set Reverb credentials (e.g. from `php artisan reverb:install`, or copy from `.env.example` and fill `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`; keep `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http` for local).
2. Start Reverb when developing: `php artisan reverb:start` (or use `composer dev`, which runs it with the API and queue).

**Frontend**

3. In frontend `.env` or env: set `NUXT_PUBLIC_REVERB_APP_KEY` to your backend `REVERB_APP_KEY`, and optionally `NUXT_PUBLIC_REVERB_HOST`, `NUXT_PUBLIC_REVERB_PORT` (default 8080), `NUXT_PUBLIC_REVERB_SCHEME` if different from defaults.

**Optional: use Pusher instead**

- Backend: set `BROADCAST_CONNECTION=pusher` and add `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`.
- Frontend: set `NUXT_PUBLIC_USE_PUSHER=true`, `NUXT_PUBLIC_PUSHER_KEY`, `NUXT_PUBLIC_PUSHER_CLUSTER`.

If neither Reverb nor Pusher is configured, the app still works; progress is shown after refresh or when you re-open a job. The frontend refetches the job list when a job completes or is partial so the Memory column in the list stays correct.

### Filament admin panel

A **Filament v5** admin panel is available at **/admin** (e.g. `http://127.0.0.1:8000/admin`). See **[FILAMENT_SETUP.md](./FILAMENT_SETUP.md)** for PHP extensions (`intl`, `zip`), installation, and creating a Filament user. The app also has a custom Admin page in the Nuxt frontend for users with `is_admin`.

### Admin dashboard (frontend)

To grant a user admin access (so they can open the frontend Admin page and see stats, all jobs, and users), run:

```bash
php artisan user:admin your@email.com
```

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
