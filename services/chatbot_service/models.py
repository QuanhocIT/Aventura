from pydantic import BaseModel
from typing import Optional


class ChatRequest(BaseModel):
    session_id: str
    message: str
    source: str = "widget"


class AdvisorChatRequest(BaseModel):
    session_id: str
    message: str
    restaurant_id: int


class ChatResponse(BaseModel):
    answer: str
    knowledge_id: Optional[int] = None
    matched_question: Optional[str] = None
    category: Optional[str] = None
    confidence: float = 0.0
    suggestions: list[str] = []
    found: bool = False


class SuggestionItem(BaseModel):
    id: int
    question: str
    category: str


class SuggestionsResponse(BaseModel):
    suggestions: list[SuggestionItem]


class FeedbackRequest(BaseModel):
    knowledge_id: int
    helpful: bool
    session_id: Optional[str] = None


class FeedbackResponse(BaseModel):
    success: bool
    message: str


class HealthResponse(BaseModel):
    status: str
    knowledge_count: int
    cache_age_seconds: Optional[float] = None
