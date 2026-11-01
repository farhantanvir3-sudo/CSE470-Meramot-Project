# Meramot Backend — Laravel 10 MVC

This is the primary backend application for the **Meramot** platform, built using **Laravel 10 (PHP 8.2)** following strict Model-View-Controller (MVC) architectural requirements for BRAC University CSE 470 (Software Engineering).

---

## ⚡ Quick Start (For Teammates)

### Prerequisites Check
1. **PHP 8.2+ with PostgreSQL extensions**:
   In your `php.ini` (e.g. `C:\xampp\php\php.ini`), verify that both PostgreSQL extensions are uncommented:
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```
2. **PostgreSQL Running**: Make sure PostgreSQL is running on port `5432` with a database named `meramot`.

### Step-by-Step Commands:
```bash
# 1. Copy environment template
cp .env.example .env

# 2. Install dependencies (from verified composer.lock)
composer install

# 3. Generate Laravel encryption key
php artisan key:generate

# 4. Run migrations and seed original 32 shops + demo users
php artisan migrate --seed

# 5. Start development server
php artisan serve --port=8000
```

> **Windows Shortcut**: You can also run `..\run-backend.bat` from the project root!

---

## Architectural Granularity (Features R1 – R20)

Every functional requirement is implemented as an isolated Single-CRUD entity across:
- **Models (`app/Models/`)**: Eloquent ORM definitions and PostgreSQL relationships
- **Controllers (`app/Http/Controllers/`)**: Action handlers with detailed `TODO` guides
- **Routes (`routes/web.php` & `routes/api.php`)**: REST API and web view endpoints
- **Views (`resources/views/`)**: Blade templates extending `layouts/app.blade.php`
- **Migrations (`database/migrations/`)**: PostgreSQL table schemas

---

## Module Testing URLs (Port 8000)

- **Shops Explore**: [http://127.0.0.1:8000/shops/explore](http://127.0.0.1:8000/shops/explore)
- **Vendor Bidding Board**: [http://127.0.0.1:8000/vendor/bidding-board](http://127.0.0.1:8000/vendor/bidding-board)
- **Rider Delivery Queue**: [http://127.0.0.1:8000/delivery/jobs-queue](http://127.0.0.1:8000/delivery/jobs-queue)
- **Admin Financial Overview**: [http://127.0.0.1:8000/admin/financial-overview](http://127.0.0.1:8000/admin/financial-overview)
- **AI Consultation Chat**: [http://127.0.0.1:8000/ai-consultant/chat](http://127.0.0.1:8000/ai-consultant/chat)
