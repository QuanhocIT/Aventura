from pydantic import BaseModel
from typing import List, Optional, Dict, Any

class OrderItemData(BaseModel):
    order_id: int
    items: List[str]

class BasketAnalysisRequest(BaseModel):
    orders: List[OrderItemData]
    min_support: float = 0.01
    min_confidence: float = 0.05

class UpsellSuggestionRequest(BaseModel):
    items: List[str]

# --- AI Fraud Detection ---
class AuditLogData(BaseModel):
    id: int
    user_id: Optional[int] = None
    user_name: str
    user_role: str
    action: str
    subject_id: Optional[int] = None
    old_values: Optional[Dict[str, Any]] = None
    new_values: Optional[Dict[str, Any]] = None
    created_at: str

class FraudDetectionRequest(BaseModel):
    logs: List[AuditLogData]

# --- Smart Inventory Prediction ---
class DailyUsageData(BaseModel):
    date: str
    quantity: float

class IngredientUsageData(BaseModel):
    ingredient_id: int
    ingredient_name: str
    sku: str
    current_stock: float
    min_stock_level: float
    unit_symbol: str
    history: List[DailyUsageData]

class InventoryForecastRequest(BaseModel):
    ingredients: List[IngredientUsageData]

# --- Revenue Forecasting ---
class HistoricalRevenueData(BaseModel):
    date: str
    net_revenue: float

class RevenueForecastRequest(BaseModel):
    history: List[HistoricalRevenueData]

# --- Supplier Price Analytics ---
class PriceHistoryData(BaseModel):
    date: str
    price: float

class PriceAnalyticsRequest(BaseModel):
    history: List[PriceHistoryData]


# --- Multi-branch Inventory Optimizer ---
class BranchInventoryData(BaseModel):
    branch_id: int
    branch_name: str
    ingredient_id: int
    ingredient_name: str
    sku: Optional[str] = None
    current_stock: float
    min_stock_level: float
    unit_symbol: str
    average_daily_usage: float

class TransferRecommendationsRequest(BaseModel):
    inventories: List[BranchInventoryData]


# --- AI Weather-based Menu Forecasting ---
class WeatherDayData(BaseModel):
    date: str
    condition: str  # sunny, rainy, cloudy, windy, cold
    temperature: float

class ProductSalesData(BaseModel):
    product_id: int
    product_name: str
    category_name: str
    avg_daily_sales: float

class WeatherMenuForecastRequest(BaseModel):
    forecast_days: List[WeatherDayData]
    products: List[ProductSalesData]


class AiInsightsRequest(BaseModel):
    restaurants: List[Dict[str, Any]]
    tenant_growth: List[Dict[str, Any]]





