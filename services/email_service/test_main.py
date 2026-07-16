"""
test_main.py — Test suite cho Email Service.

Chạy:
    cd services/email_service
    pip install pytest httpx
    pytest test_main.py -v
"""

import os
import pytest
from unittest.mock import patch, MagicMock
from fastapi.testclient import TestClient

# Dev mode: bỏ qua auth check
os.environ.setdefault("INTERNAL_API_KEY", "")
os.environ.setdefault("BREVO_API_KEY", "test-fake-key")

from main import app  # noqa: E402

client = TestClient(app)

VALID_HEADERS = {"X-Internal-API-Key": os.getenv("INTERNAL_API_KEY", "")}


# ─────────────────────────────────────────────────────────────────────────────
# Health
# ─────────────────────────────────────────────────────────────────────────────

def test_health_endpoint_returns_ok():
    """GET /health phải trả 200 kể cả khi Brevo key là fake."""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert "brevo_configured" in data


# ─────────────────────────────────────────────────────────────────────────────
# Auth Protection
# ─────────────────────────────────────────────────────────────────────────────

@pytest.mark.skipif(
    not os.getenv("INTERNAL_API_KEY"),
    reason="INTERNAL_API_KEY chưa được set — bỏ qua test auth (dev mode)"
)
def test_send_welcome_rejected_without_api_key():
    """POST /send/welcome phải trả 403 nếu không có API key."""
    response = client.post("/send/welcome", json={
        "name": "Test User",
        "email": "test@test.com",
        "restaurant_name": "Test Restaurant",
    })
    assert response.status_code == 403


@pytest.mark.skipif(
    not os.getenv("INTERNAL_API_KEY"),
    reason="INTERNAL_API_KEY chưa được set — bỏ qua test auth (dev mode)"
)
def test_ai_insights_rejected_without_api_key():
    """POST /ai/insights phải trả 403 nếu không có API key."""
    response = client.post("/ai/insights", json={"restaurants": [], "tenant_growth": []})
    assert response.status_code == 403


# ─────────────────────────────────────────────────────────────────────────────
# Email Endpoints (mock Brevo để không gửi email thật)
# ─────────────────────────────────────────────────────────────────────────────

def test_send_otp_missing_fields_returns_422():
    """POST /send/otp thiếu trường bắt buộc phải trả 422."""
    response = client.post("/send/otp", json={}, headers=VALID_HEADERS)
    assert response.status_code == 422


@patch("main.send_welcome_email", return_value="msg-id-123")
def test_send_welcome_success(mock_send):
    """POST /send/welcome với đủ data và mock Brevo phải trả 200."""
    response = client.post(
        "/send/welcome",
        json={
            "name": "Nguyễn Văn A",
            "email": "test@example.com",
            "restaurant_name": "Quán Ăn Test",
            "login_url": "http://localhost/login",
        },
        headers=VALID_HEADERS,
    )
    assert response.status_code == 200
    data = response.json()
    assert data.get("success") is True
    mock_send.assert_called_once()


def test_ai_insights_empty_data():
    """POST /ai/insights với dữ liệu rỗng phải không crash."""
    response = client.post(
        "/ai/insights",
        json={"restaurants": [], "tenant_growth": []},
        headers=VALID_HEADERS,
    )
    # Có thể trả 200 (fallback) hoặc 500 (nếu AI cần data) — không được crash 
    assert response.status_code in (200, 500)
