@echo off
echo ===============================================================
echo     MERAMOT CSE470 — LOCAL ENVIRONMENT ONE-CLICK SETUP
echo ===============================================================

set PHP_BIN=C:\xampp\php\php.exe
set COMPOSER_BIN=C:\xampp\php\composer.bat

cd /d "%~dp0\backend"
echo [1/4] Preparing Backend .env configuration...
if not exist .env copy .env.example .env

echo [2/4] Installing Laravel dependencies...
call "%COMPOSER_BIN%" install --no-interaction

echo [3/4] Generating Application Key...
call "%PHP_BIN%" artisan key:generate

echo [4/4] Setting up Python AI Microservice...
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
echo ===============================================================
pause
