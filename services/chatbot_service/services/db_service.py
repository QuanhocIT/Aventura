import logging
import pymysql
import pymysql.cursors
from config import DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

logger = logging.getLogger(__name__)


def _get_connection():
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USERNAME,
        password=DB_PASSWORD,
        database=DB_DATABASE,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=5,
    )


def fetch_active_knowledge() -> list[dict]:
    """Load all active Q&A pairs from DB."""
    conn = _get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT id, category, question, answer,
                       alt_questions, keywords, suggested_questions,
                       view_count, helpful_count, unhelpful_count, display_order
                FROM chatbot_knowledge
                WHERE is_active = 1
                ORDER BY display_order ASC, id ASC
                """
            )
            rows = cursor.fetchall()
            return list(rows)
    except Exception as e:
        logger.error("DB fetch error: %s", e)
        return []
    finally:
        conn.close()


def increment_view(knowledge_id: int) -> None:
    conn = _get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                "UPDATE chatbot_knowledge SET view_count = view_count + 1 WHERE id = %s",
                (knowledge_id,),
            )
        conn.commit()
    except Exception as e:
        logger.warning("increment_view failed: %s", e)
    finally:
        conn.close()


def record_feedback(knowledge_id: int, helpful: bool) -> None:
    conn = _get_connection()
    try:
        col = "helpful_count" if helpful else "unhelpful_count"
        with conn.cursor() as cursor:
            cursor.execute(
                f"UPDATE chatbot_knowledge SET {col} = {col} + 1 WHERE id = %s",
                (knowledge_id,),
            )
        conn.commit()
    except Exception as e:
        logger.warning("record_feedback failed: %s", e)
    finally:
        conn.close()


def log_unanswered_query(
    query: str,
    best_score: float,
    session_id: str | None,
    user_id: int | None,
    restaurant_id: int | None,
    source: str,
) -> None:
    """Ghi nhận câu hỏi không được trả lời vào DB — upsert theo hash câu hỏi."""
    import hashlib
    clean_query = query.strip()[:500]
    query_hash = hashlib.sha256(clean_query.lower().encode("utf-8")).hexdigest()[:64]

    conn = _get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                INSERT INTO chatbot_unanswered_queries
                    (query, query_hash, best_score, session_id, user_id, restaurant_id, source,
                     occurrence_count, last_seen_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 1, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    occurrence_count = occurrence_count + 1,
                    best_score       = GREATEST(best_score, VALUES(best_score)),
                    last_seen_at     = NOW(),
                    updated_at       = NOW()
                """,
                (clean_query, query_hash, best_score, session_id, user_id, restaurant_id, source),
            )
        conn.commit()
    except Exception as e:
        logger.warning("log_unanswered_query failed: %s", e)
    finally:
        conn.close()


def get_system_setting(key: str, default: str) -> str:
    """Get a system setting value by key, fallback to a default."""
    conn = _get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT value FROM system_settings WHERE `key` = %s", (key,))
            row = cursor.fetchone()
            return row["value"] if row and row["value"] is not None else default
    except Exception as e:
        logger.error("DB get_system_setting error: %s", e)
        return default
    finally:
        conn.close()
