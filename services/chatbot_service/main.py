import logging
from fastapi import FastAPI, HTTPException, Query, Depends
from fastapi.middleware.cors import CORSMiddleware
from typing import Optional

from models import (
    ChatRequest, ChatResponse,
    SuggestionsResponse, SuggestionItem,
    FeedbackRequest, FeedbackResponse,
    HealthResponse,
    AdvisorChatRequest,
    LogUnansweredRequest,
    TestQueryRequest, TestQueryResponse,
)
from services import nlp_service
from services.db_service import increment_view, record_feedback, log_unanswered_query
import time
from auth import require_api_key

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

# Dependency bảo mật — áp dụng lên các route cần xác thực nội bộ
_auth = [Depends(require_api_key)]


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


@app.post("/chat", response_model=ChatResponse, dependencies=_auth)
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
    elif not result["found"]:
        # Ghi nhận câu hỏi không trả lời được vào bảng diagnostics
        try:
            log_unanswered_query(
                query=message,
                best_score=result["confidence"],
                session_id=payload.session_id,
                user_id=None,
                restaurant_id=None,
                source=payload.source,
            )
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


@app.post("/advisor-chat", response_model=ChatResponse, dependencies=_auth)
def advisor_chat(payload: AdvisorChatRequest):
    """Xử lý câu hỏi của Chủ doanh nghiệp và trả về phân tích nghiệp vụ."""
    if not payload.message or not payload.message.strip():
        raise HTTPException(status_code=422, detail="Tin nhắn không được để trống.")

    message = payload.message.strip()[:500]

    try:
        result = nlp_service.match_advisor_query(message, payload.restaurant_id)
    except Exception as e:
        logger.error("Advisor NLP error: %s", e)
        raise HTTPException(status_code=500, detail="Lỗi xử lý câu hỏi tư vấn.")

    logger.info(
        "advisor_chat | session=%s restaurant=%d",
        payload.session_id[:12],
        payload.restaurant_id,
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


@app.post("/feedback", response_model=FeedbackResponse, dependencies=_auth)
def feedback(payload: FeedbackRequest):
    """Ghi nhận phản hồi hữu ích / không hữu ích của người dùng."""
    try:
        record_feedback(payload.knowledge_id, payload.helpful)
    except Exception as e:
        logger.warning("feedback record failed: %s", e)
        return FeedbackResponse(success=False, message="Không thể ghi nhận phản hồi.")

    return FeedbackResponse(success=True, message="Cảm ơn phản hồi của bạn!")


@app.post("/reload-cache", dependencies=_auth)
def reload_cache():
    """Force reload NLP cache từ DB (gọi sau khi admin cập nhật knowledge base)."""
    try:
        nlp_service.reload_cache()
        from services.nlp_service import _knowledge_rows
        return {"success": True, "knowledge_count": len(_knowledge_rows)}
    except Exception as e:
        logger.error("reload-cache error: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/log-unanswered", dependencies=_auth)
def log_unanswered(payload: LogUnansweredRequest):
    """Laravel gọi endpoint này để ghi nhận câu hỏi không có câu trả lời kèm user context."""
    try:
        log_unanswered_query(
            query=payload.query,
            best_score=payload.best_score,
            session_id=payload.session_id,
            user_id=payload.user_id,
            restaurant_id=payload.restaurant_id,
            source=payload.source,
        )
        return {"success": True}
    except Exception as e:
        logger.warning("log-unanswered error: %s", e)
        return {"success": False}


@app.post("/test-query", response_model=TestQueryResponse, dependencies=_auth)
def test_query(payload: TestQueryRequest):
    """Chạy matching và trả về điểm phân tích chi tiết — dùng cho Playground của Superadmin."""
    if not payload.query or not payload.query.strip():
        raise HTTPException(status_code=422, detail="Query không được để trống.")

    try:
        result = nlp_service.test_query_detail(payload.query.strip())
        return TestQueryResponse(**result)
    except Exception as e:
        logger.error("test-query error: %s", e)
        raise HTTPException(status_code=500, detail=str(e))
