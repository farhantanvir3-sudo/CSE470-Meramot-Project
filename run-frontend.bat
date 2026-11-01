@echo off
cd /d "%~dp0\frontend"
echo ===============================================================
echo   Compiling Tailwind CSS & Starting Watcher...
echo ===============================================================
npm run watch:css
pause
