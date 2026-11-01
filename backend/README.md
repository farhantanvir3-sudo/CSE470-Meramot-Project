# Meramot Backend — Laravel 10 MVC

This is the primary backend application for the **Meramot** platform, built using **Laravel 10 (PHP 8.2)** following strict Model-View-Controller (MVC) architectural requirements for BRAC University CSE 470 (Software Engineering).

## Architectural Granularity (Features R1 – R20)

Every functional requirement is implemented as an isolated Single-CRUD entity across:
- **Models (`app/Models/`)**: Eloquent ORM definitions and PostgreSQL relationships
- **Controllers (`app/Http/Controllers/`)**: Action handlers for the assigned module features
- **Routes (`routes/web.php` & `routes/api.php`)**: REST API and web view endpoints
- **Migrations (`database/migrations/`)**: PostgreSQL table schemas

## Quick Start

1. Copy environment template:
   ```bash
   cp .env.example .env
   ```
2. Configure PostgreSQL credentials in `.env`:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=meramot_db
   DB_USERNAME=postgres
   DB_PASSWORD=your_password
   ```
3. Install dependencies & run migrations:
   ```bash
   composer install
   php artisan key:generate
   php artisan migrate
   ```
4. Start development server:
   ```bash
   php artisan serve --port=8000
   ```
