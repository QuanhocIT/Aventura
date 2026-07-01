from fastapi.testclient import TestClient
from main import app
from security import verify_internal_key

app.dependency_overrides[verify_internal_key] = lambda: None
client = TestClient(app)

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
    response = client.post("/api/analytics/weather-menu-forecast", json=payload)
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
    response = client.post("/api/analytics/weather-menu-forecast", json=payload)
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
