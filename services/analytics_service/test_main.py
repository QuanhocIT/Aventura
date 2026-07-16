"""
test_main.py — Test suite cho Analytics Service.

Chạy:
    cd services/analytics_service
    pip install pytest httpx
    pytest test_main.py -v
"""

import os
import pytest
from fastapi.testclient import TestClient

# Dev mode: bỏ qua auth check
os.environ.setdefault("INTERNAL_API_KEY", "")

from main import app  # noqa: E402

client = TestClient(app)

VALID_HEADERS = {"X-Internal-API-Key": os.getenv("INTERNAL_API_KEY", "")}


# ─────────────────────────────────────────────────────────────────────────────
# Health & Root
# ─────────────────────────────────────────────────────────────────────────────

def test_root_returns_online_status():
    """GET / phải trả về service status."""
    response = client.get("/")
    assert response.status_code == 200
    data = response.json()
    assert data.get("status") == "online"


# ─────────────────────────────────────────────────────────────────────────────
# Auth Protection
# ─────────────────────────────────────────────────────────────────────────────

@pytest.mark.skipif(
    not os.getenv("INTERNAL_API_KEY"),
    reason="INTERNAL_API_KEY chưa được set — bỏ qua test auth (dev mode)"
)
def test_basket_analysis_rejected_without_api_key():
    """POST /api/analytics/basket-analysis phải trả 403 khi thiếu API key."""
    response = client.post("/api/analytics/basket-analysis", json={"orders": []})
    assert response.status_code == 403


@pytest.mark.skipif(
    not os.getenv("INTERNAL_API_KEY"),
    reason="INTERNAL_API_KEY chưa được set — bỏ qua test auth (dev mode)"
)
def test_fraud_detection_rejected_without_api_key():
    """POST /api/analytics/fraud-detection phải trả 403 khi thiếu API key."""
    response = client.post("/api/analytics/fraud-detection", json={"audit_logs": []})
    assert response.status_code == 403


# ─────────────────────────────────────────────────────────────────────────────
# Business Logic Tests (giữ nguyên từ ban đầu)
# ─────────────────────────────────────────────────────────────────────────────

def test_weather_menu_forecast_rainy():
    payload = {
        "forecast_days": [
            {"date": "2026-06-14", "condition": "rainy", "temperature": 19.5}
        ],
        "products": [
            {"product_id": 1, "product_name": "Lẩu riêu cua", "category_name": "Lẩu", "avg_daily_sales": 10.0},
            {"product_id": 2, "product_name": "Bia Hà Nội", "category_name": "Đồ uống lạnh", "avg_daily_sales": 15.0}
        ]
    }
    response = client.post("/api/analytics/weather-menu-forecast", json=payload, headers=VALID_HEADERS)
    assert response.status_code == 200
    data = response.json()
    assert data["success"] is True
    assert len(data["forecast"]) == 1
    
    day_forecast = data["forecast"][0]
    assert day_forecast["condition"] == "rainy"
    assert day_forecast["temperature"] == 19.5
    
    recs = day_forecast["recommendations"]
    assert len(recs) == 2
    
    # Lẩu should have increased demand
    hotpot_rec = next(r for r in recs if r["product_id"] == 1)
    assert hotpot_rec["suggested_multiplier"] > 1.0
    assert hotpot_rec["change_pct"] > 0
    
    # Beer should have decreased demand
    beer_rec = next(r for r in recs if r["product_id"] == 2)
    assert beer_rec["suggested_multiplier"] < 1.0
    assert beer_rec["change_pct"] < 0

def test_weather_menu_forecast_sunny():
    payload = {
        "forecast_days": [
            {"date": "2026-06-15", "condition": "sunny", "temperature": 33.0}
        ],
        "products": [
            {"product_id": 1, "product_name": "Lẩu thái chua cay", "category_name": "Lẩu", "avg_daily_sales": 8.0},
            {"product_id": 2, "product_name": "Nước mía cốt dừa", "category_name": "Nước giải khát", "avg_daily_sales": 20.0}
        ]
    }
    response = client.post("/api/analytics/weather-menu-forecast", json=payload, headers=VALID_HEADERS)
    assert response.status_code == 200
    data = response.json()
    assert data["success"] is True
    assert len(data["forecast"]) == 1
    
    day_forecast = data["forecast"][0]
    assert day_forecast["condition"] == "sunny"
    assert day_forecast["temperature"] == 33.0
    
    recs = day_forecast["recommendations"]
    assert len(recs) == 2
    
    # Lẩu should have decreased demand due to sunny heat
    hotpot_rec = next(r for r in recs if r["product_id"] == 1)
    assert hotpot_rec["suggested_multiplier"] < 1.0
    assert hotpot_rec["change_pct"] < 0
    
    # Cold drink should have increased demand
    drink_rec = next(r for r in recs if r["product_id"] == 2)
    assert drink_rec["suggested_multiplier"] > 1.0
    assert drink_rec["change_pct"] > 0
