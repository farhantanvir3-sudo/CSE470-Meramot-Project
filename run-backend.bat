@echo off
set PHP_BIN=C:\xampp\php\php.exe
if not exist "%PHP_BIN%" set PHP_BIN=php

cd /d "%~dp0\backend"
echo ===============================================================
echo   Starting Meramot Laravel 10 MVC Server (Port 8000)...
echo   URL: http://127.0.0.1:8000
echo ===============================================================
"%PHP_BIN%" artisan serve --port=8000
pause
