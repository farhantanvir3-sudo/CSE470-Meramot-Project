@echo off
setlocal
echo ===============================================================
echo     MERAMOT CSE470 — LOCAL ENVIRONMENT ONE-CLICK SETUP
echo ===============================================================

git -C "%~dp0" config core.hooksPath .githooks >nul 2>&1

set PHP_BIN=C:\xampp\php\php.exe
if not exist "%PHP_BIN%" set PHP_BIN=php

set COMPOSER_BIN=C:\xampp\php\composer.bat
if not exist "%COMPOSER_BIN%" set COMPOSER_BIN=composer

cd /d "%~dp0\backend"
echo [1/5] Preparing Backend .env configuration...
if not exist .env copy .env.example .env

echo [2/5] Installing Laravel dependencies...
call "%COMPOSER_BIN%" install --no-interaction

echo [3/5] Generating Application Key...
call "%PHP_BIN%" artisan key:generate

echo [4/5] Initializing Local PostgreSQL Database...
call "%PHP_BIN%" create-db.php
if %ERRORLEVEL% equ 0 (
    echo Running migrations and seeders...
    call "%PHP_BIN%" artisan migrate --seed --force
) else (
    echo.
    echo [NOTE] Automatic DB initialization skipped. 
    echo Once PostgreSQL is running and your backend\.env credentials are set,
    echo simply double-click 'setup-db.bat' to migrate and seed the database.
    echo.
)

echo [5/5] Setting up Python AI Microservice...
cd /d "%~dp0\backend\ai-microservice"
if not exist venv (
    python -m venv venv
)
call venv\Scripts\activate.bat
pip install -r requirements.txt
if not exist .env copy .env.example .env

echo.
echo ===============================================================
echo   SETUP COMPLETED! You can now run the servers with:
echo   - run-backend.bat (Laravel Backend on port 8000)
echo   - run-ai.bat      (Python FastAPI on port 5000)
echo   - setup-db.bat    (Re-run migrations and seeds anytime)
echo ===============================================================
pause
