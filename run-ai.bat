@echo off
cd /d "%~dp0\backend\ai-microservice"
if exist venv\Scripts\activate.bat (
    call venv\Scripts\activate.bat
)
echo ===============================================================
echo   Starting Meramot Python FastAPI AI Microservice (Port 5000)...
echo   URL: http://127.0.0.1:5000
echo   Docs: http://127.0.0.1:5000/docs
echo ===============================================================
python -m uvicorn main:app --reload --port 5000
pause
