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
    branch_id: Optional[int] = None


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


class LogUnansweredRequest(BaseModel):
    query: str
    best_score: float = 0.0
    session_id: Optional[str] = None
    user_id: Optional[int] = None
    restaurant_id: Optional[int] = None
    source: str = "widget"


class TestQueryRequest(BaseModel):
    query: str


class TestQueryResponse(BaseModel):
    found: bool
    confidence: float
    matched_question: Optional[str] = None
    category: Optional[str] = None
    answer: Optional[str] = None
    char_score: float = 0.0
    word_score: float = 0.0
    bm25_score: float = 0.0
    keyword_score: float = 0.0
    threshold_used: float = 0.0
