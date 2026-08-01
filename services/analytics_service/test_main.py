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


# ─────────────────────────────────────────────────────────────────────────────
# Cohort Retention Analysis
# ─────────────────────────────────────────────────────────────────────────────

def test_cohort_retention_empty_restaurants_returns_empty_list():
    response = client.post(
        "/api/analytics/cohort-retention",
        json={"restaurants": [], "order_activity": [], "months": 6, "now": "2026-07-15"},
        headers=VALID_HEADERS,
    )
    assert response.status_code == 200
    assert response.json() == {"cohorts": []}


def test_cohort_retention_computes_percentage_and_nulls_future_checkpoints():
    payload = {
        "restaurants": [
            {"restaurant_id": 1, "created_at": "2026-05-10", "status": "active"},
            {"restaurant_id": 2, "created_at": "2026-05-20", "status": "active"},
        ],
        "order_activity": [
            {"restaurant_id": 1, "order_date": "2026-06-15"},
        ],
        "months": 3,
        "now": "2026-07-15",
    }
    response = client.post("/api/analytics/cohort-retention", json=payload, headers=VALID_HEADERS)
    assert response.status_code == 200
    data = response.json()

    cohorts = data["cohorts"]
    assert len(cohorts) == 3
    assert [c["month"] for c in cohorts] == ["2026-05", "2026-06", "2026-07"]

    may_cohort = cohorts[0]
    assert may_cohort["total"] == 2
    # Chỉ nhà hàng 1 (active) có đơn hàng trong khung M+1 (tháng 6) -> 1/2 = 50%.
    assert may_cohort["m1"] == 50.0
    # M+3 (tháng 8) và M+6 (tháng 11) đều vượt quá "now" (15/07) -> chưa xác định.
    assert may_cohort["m3"] is None
    assert may_cohort["m6"] is None

    june_cohort = cohorts[1]
    assert june_cohort["total"] == 0
    # Không có nhà hàng nào trong cohort -> tỉ lệ giữ chân bằng 0%, không phải null,
    # vì mốc M+1 (tháng 7) đã tới nhưng cohort rỗng.
    assert june_cohort["m1"] == 0.0

    july_cohort = cohorts[2]
    assert july_cohort["total"] == 0
    # Mốc M+1 (tháng 8) của cohort tháng 7 vẫn ở tương lai so với "now".
    assert july_cohort["m1"] is None


def test_cohort_retention_excludes_non_active_restaurants_from_retained_count():
    payload = {
        "restaurants": [
            {"restaurant_id": 1, "created_at": "2026-05-10", "status": "expired"},
        ],
        "order_activity": [
            {"restaurant_id": 1, "order_date": "2026-06-15"},
        ],
        "months": 1,
        "now": "2026-07-15",
    }
    # months=1 chỉ có cohort tháng 7 (rỗng) — đổi sang kiểm tra trực tiếp cohort tháng 5
    # bằng months=3 và chỉ xét phần tử đầu tiên.
    payload["months"] = 3
    response = client.post("/api/analytics/cohort-retention", json=payload, headers=VALID_HEADERS)
    assert response.status_code == 200

    may_cohort = response.json()["cohorts"][0]
    assert may_cohort["total"] == 1
    # Nhà hàng duy nhất trong cohort đã "expired" nên không tính vào retained,
    # dù có đơn hàng trong khung M+1 -> 0%, không phải null (mốc đã qua).
    assert may_cohort["m1"] == 0.0
