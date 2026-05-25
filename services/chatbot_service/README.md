# Chatbot Service

FastAPI microservice for Aventura's AI chatbot (BM25 + TF-IDF ensemble NLP).

## Requirements

- Python 3.10 at `C:\laragon\bin\python\python-3.10\python.exe`
- Dependencies: see `requirements.txt`

## Install dependencies

```powershell
C:\laragon\bin\python\python-3.10\python.exe -m pip install --user -r requirements.txt
```

## Start the service

```powershell
.\start.ps1
```

This script kills any process already on port 8002, then launches uvicorn with `--reload`.

## Environment variables

Create a `.env` file in this directory (or set in your shell):

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aventura
DB_USERNAME=root
DB_PASSWORD=
```

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Health check |
| POST | `/chat` | Send a message, get AI reply |
| POST | `/sessions` | Start a new session |
| GET | `/sessions/{id}` | Get session history |

## Port

Default: **8002** — configured in `main.py` and `.env` (Laravel side: `CHATBOT_SERVICE_URL`).
