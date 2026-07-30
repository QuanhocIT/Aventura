"""
test_main.py — Test suite cho Chatbot Service.

Chạy:
    cd services/chatbot_service
    pip install pytest httpx
    pytest test_main.py -v
"""

import os
import pytest
from fastapi.testclient import TestClient

# Đảm bảo test chạy không cần DB thật (override env trước khi import main)
os.environ.setdefault("DB_HOST", "127.0.0.1")
os.environ.setdefault("INTERNAL_API_KEY", "")  # Dev mode: bỏ qua auth check

from main import app  # noqa: E402

client = TestClient(app)

VALID_HEADERS = {"X-Internal-API-Key": os.getenv("INTERNAL_API_KEY", "")}


# ─────────────────────────────────────────────────────────────────────────────
# Health & Startup
# ─────────────────────────────────────────────────────────────────────────────

def test_health_endpoint_returns_ok():
    """GET /health phải luôn trả về 200 kể cả khi DB chưa có data."""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert "status" in data


# ─────────────────────────────────────────────────────────────────────────────
# Auth Protection (chỉ test khi INTERNAL_API_KEY được set)
# ─────────────────────────────────────────────────────────────────────────────

@pytest.mark.skipif(
    not os.getenv("INTERNAL_API_KEY"),
    reason="INTERNAL_API_KEY chưa được set — bỏ qua test auth (dev mode)"
)
def test_chat_endpoint_rejected_without_api_key():
    """POST /chat phải trả 403 nếu không có API key header khi production mode."""
    response = client.post("/chat", json={"message": "xin chào", "session_id": "test_session"})
    assert response.status_code == 403


@pytest.mark.skipif(
    not os.getenv("INTERNAL_API_KEY"),
    reason="INTERNAL_API_KEY chưa được set — bỏ qua test auth (dev mode)"
)
def test_chat_endpoint_accepted_with_valid_api_key():
    """POST /chat phải trả 200 khi có API key hợp lệ."""
    response = client.post(
        "/chat",
        json={"message": "Aventura là gì?", "session_id": "test_session"},
        headers=VALID_HEADERS,
    )
    assert response.status_code == 200


# ─────────────────────────────────────────────────────────────────────────────
# Chat Logic (dev mode — không cần API key)
# ─────────────────────────────────────────────────────────────────────────────

def test_chat_with_empty_message_returns_422():
    """POST /chat với message rỗng phải trả 422."""
    response = client.post(
        "/chat",
        json={"message": "", "session_id": "test_session"},
        headers=VALID_HEADERS
    )
    assert response.status_code == 422


def test_chat_with_valid_message_returns_response_structure():
    """POST /chat với message hợp lệ phải trả về đúng cấu trúc JSON."""
    response = client.post(
        "/chat",
        json={"message": "Xin chào, Aventura có tính năng gì?", "session_id": "test_session"},
        headers=VALID_HEADERS,
    )
    assert response.status_code == 200
    data = response.json()
    assert "answer" in data
    assert "confidence" in data


def test_advisor_chat_requires_restaurant_id():
    """POST /advisor-chat phải trả 422 nếu thiếu restaurant_id."""
    response = client.post(
        "/advisor-chat",
        json={"message": "doanh thu hôm nay", "session_id": "test_session"},
        headers=VALID_HEADERS,
    )
    assert response.status_code in (422, 400)


def test_reload_cache_endpoint_accessible():
    """POST /reload-cache phải trả 200."""
    response = client.post("/reload-cache", headers=VALID_HEADERS)
    assert response.status_code == 200
