from pydantic import BaseModel
from typing import List

class OrderItemData(BaseModel):
    order_id: int
    items: List[str]

class BasketAnalysisRequest(BaseModel):
    orders: List[OrderItemData]
    min_support: float = 0.01
    min_confidence: float = 0.05

class UpsellSuggestionRequest(BaseModel):
    items: List[str]
