@echo off
setlocal
cd /d "%~dp0\backend\ai-microservice"
if exist venv\Scripts\activate.bat (
    call venv\Scripts\activate.bat
)

echo ===============================================================
echo   Starting Meramot Python FastAPI AI Microservice (Port 5000)...
echo   URL: http://127.0.0.1:5000
echo   Docs: http://127.0.0.1:5000/docs
echo ===============================================================

:: Check if port 5000 is already occupied by another instance
netstat -ano | findstr ":5000" | findstr "LISTENING" >nul
if %ERRORLEVEL% equ 0 (
    echo.
    echo [NOTE] Port 5000 is already held by another running instance.
    echo Starting microservice on fallback port 5001...
    echo URL: http://127.0.0.1:5001
    echo Docs: http://127.0.0.1:5001/docs
    echo ===============================================================
    python -m uvicorn main:app --reload --port 5001
    goto :done
)

python -m uvicorn main:app --reload --port 5000

:done
pause
