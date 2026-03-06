# Filament v5 setup (admin panel)

## 1. Enable required PHP extensions (XAMPP)

Filament needs **intl** and **zip**. In **`C:\xampp\php\php.ini`**:

1. Enable **intl** – find `;extension=intl` and change to:
   ```ini
   extension=intl
   ```
2. Enable **zip** – find `;extension=zip` and change to:
   ```ini
   extension=zip
   ```
3. Save, restart Apache (or your PHP server), then check:
   ```bash
   php -m | findstr intl
   php -m | findstr zip
   ```
   Both should appear.

## 2. Install Filament v5 (Laravel 12 compatible)

From the **backend** directory run:

```bash
cd backend
composer require filament/filament:^5
```

Using `:^5` forces Filament 5, which works with Laravel 12 and avoids the older Filament 3 packages that have security advisories.

## 3. Install the panel

```bash
php artisan filament:install --panels
```

## 4. Create an admin user

```bash
php artisan make:filament-user
```

Then open **http://your-backend-url/admin** (e.g. `http://127.0.0.1:8000/admin`) and log in.

**Current state:** The panel is installed with **admin-only access** (only users with `is_admin` can log in; see `App\Models\User` implementing `FilamentUser::canAccessPanel()`). Two **Filament resources** are available: **Users** (list, create, edit, view; jobs count, admin toggle) and **Measurement Jobs** (list, view; no create—jobs are submitted via the API). The project also has a custom **Admin** page in the Nuxt frontend (stats, all jobs, users).

---

**If you cannot enable `intl` right now** (not recommended long term), you can temporarily skip the check:

```bash
composer require filament/filament:^5 --ignore-platform-req=ext-intl
```

Some Filament features (e.g. locale/formatting) may not work correctly without `intl`. Prefer enabling the extension when you can.
