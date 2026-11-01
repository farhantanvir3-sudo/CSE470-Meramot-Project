# Meramot AI Microservice (FastAPI)

Isolated Python microservice providing LLM-driven device diagnostic summarization and troubleshooting assistance.

- **Assigned Feature R13**: Hardware Fault & Request Summarization
- **Assigned Feature R10**: Interactive Troubleshooting AI Chat
- **Lead Developer**: Farhan Tanvir [22299179]
- **Port**: `5000`

## Installation & Execution

```bash
# 1. Create Python virtual environment
python -m venv venv

# 2. Activate virtual environment
# Windows:
.\venv\Scripts\activate
# Linux/macOS:
# source venv/bin/activate

# 3. Install packages
pip install -r requirements.txt

# 4. Copy env file & set API keys
cp .env.example .env

# 5. Start development server
uvicorn main:app --reload --port 5000
```
