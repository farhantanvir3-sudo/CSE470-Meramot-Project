@echo off
setlocal
echo ===============================================================
echo     MERAMOT CSE470 — LOCAL POSTGRESQL DATABASE SETUP
echo ===============================================================

git -C "%~dp0" config core.hooksPath .githooks >nul 2>&1

set PHP_BIN=C:\xampp\php\php.exe
if not exist "%PHP_BIN%" set PHP_BIN=php

cd /d "%~dp0\backend"

if not exist .env (
    echo [1/3] Creating backend .env from .env.example...
    copy .env.example .env
)

echo [1/3] Checking / Creating PostgreSQL Database...
"%PHP_BIN%" create-db.php
if %ERRORLEVEL% neq 0 (
    echo.
    echo ===============================================================
    echo [ATTENTION] Could not automatically connect to PostgreSQL!
    echo.
    echo Please check your backend\.env file:
    echo   1. Ensure PostgreSQL is running on port 5432.
    echo   2. Set DB_USERNAME to your local user (e.g., postgres).
    echo   3. Set DB_PASSWORD to your PostgreSQL password.
    echo.
    echo Alternatively, if your team provides a Postgres URL:
    echo   DATABASE_URL=postgresql://user:password@localhost:5432/meramot
    echo ===============================================================
    pause
    exit /b 1
)

echo.
echo [2/3] Running Database Migrations (39 Tables)...
"%PHP_BIN%" artisan migrate --force

echo.
echo [3/3] Seeding Initial Data (Shops, Packages, Mock Accounts)...
"%PHP_BIN%" artisan db:seed --force

echo.
echo ===============================================================
echo   SUCCESS! Database 'meramot' is fully migrated and seeded!
echo   You can now launch the app with run-backend.bat.
echo ===============================================================
pause
