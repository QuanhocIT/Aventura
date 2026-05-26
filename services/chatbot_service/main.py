import logging
from fastapi import FastAPI, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware
from typing import Optional

from models import (
    ChatRequest, ChatResponse,
    SuggestionsResponse, SuggestionItem,
    FeedbackRequest, FeedbackResponse,
    HealthResponse,
)
from services import nlp_service
from services.db_service import increment_view, record_feedback
import time

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Aventura Chatbot Service",
    description="Python FAQ chatbot microservice cho Aventura SaaS",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


@app.on_event("startup")
def startup_event():
    logger.info("Chatbot service starting — loading NLP cache...")
    nlp_service.reload_cache()
    logger.info("NLP cache ready.")


@app.get("/health", response_model=HealthResponse)
def health_check():
    from services.nlp_service import _knowledge_rows, _cache_time
    age = round(time.time() - _cache_time, 1) if _cache_time else None
    return HealthResponse(
        status="ok",
        knowledge_count=len(_knowledge_rows),
        cache_age_seconds=age,
    )


@app.post("/chat", response_model=ChatResponse)
def chat(payload: ChatRequest):
    """Xử lý câu hỏi người dùng và trả về câu trả lời phù hợp nhất."""
    if not payload.message or not payload.message.strip():
        raise HTTPException(status_code=422, detail="Tin nhắn không được để trống.")

    message = payload.message.strip()[:500]  # giới hạn độ dài

    try:
        result = nlp_service.match_question(message)
    except Exception as e:
        logger.error("NLP error: %s", e)
        raise HTTPException(status_code=500, detail="Lỗi xử lý câu hỏi.")

    # Ghi view count bất đồng bộ (fire-and-forget style — không block response)
    if result["found"] and result.get("knowledge_id"):
        try:
            increment_view(result["knowledge_id"])
        except Exception:
            pass

    logger.info(
        "chat | session=%s found=%s confidence=%.3f category=%s",
        payload.session_id[:12],
        result["found"],
        result["confidence"],
        result.get("category"),
    )

    return ChatResponse(**result)


@app.get("/suggestions", response_model=SuggestionsResponse)
def suggestions(
    category: Optional[str] = Query(default=None),
    limit: int = Query(default=5, ge=1, le=20),
):
    """Trả về danh sách câu hỏi gợi ý để hiển thị khi mở chatbot."""
    try:
        items = nlp_service.get_popular_suggestions(category=category, limit=limit)
    except Exception as e:
        logger.error("suggestions error: %s", e)
        raise HTTPException(status_code=500, detail="Lỗi lấy gợi ý.")

    return SuggestionsResponse(
        suggestions=[SuggestionItem(**item) for item in items]
    )


@app.post("/feedback", response_model=FeedbackResponse)
def feedback(payload: FeedbackRequest):
    """Ghi nhận phản hồi hữu ích / không hữu ích của người dùng."""
    try:
        record_feedback(payload.knowledge_id, payload.helpful)
    except Exception as e:
        logger.warning("feedback record failed: %s", e)
        return FeedbackResponse(success=False, message="Không thể ghi nhận phản hồi.")

    return FeedbackResponse(success=True, message="Cảm ơn phản hồi của bạn!")


@app.post("/reload-cache")
def reload_cache():
    """Force reload NLP cache từ DB (gọi sau khi admin cập nhật knowledge base)."""
    try:
        nlp_service.reload_cache()
        from services.nlp_service import _knowledge_rows
        return {"success": True, "knowledge_count": len(_knowledge_rows)}
    except Exception as e:
        logger.error("reload-cache error: %s", e)
        raise HTTPException(status_code=500, detail=str(e))
