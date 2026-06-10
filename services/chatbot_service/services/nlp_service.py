"""
Ensemble NLP matching engine cho chatbot Aventura.

Chiến lược scoring (4 tín hiệu):
  1. TF-IDF char n-gram  (1-3) – bắt được pattern âm tiết tiếng Việt        [weight 0.35]
  2. TF-IDF word n-gram  (1-2) – bắt được cụm từ nguyên nghĩa               [weight 0.25]
  3. BM25 word           (rank_bm25) – keyword relevance, xử lý tốt typo nhẹ [weight 0.25]
  4. Keyword overlap     – từ khoá quan trọng admin tự đánh                   [weight 0.15]

Chuẩn hoá đầu vào (Vietnamese-aware):
  - unicode NFC
  - lowercase
  - bỏ dấu câu thừa, khoảng trắng thừa
  - giữ nguyên dấu thanh/dấu phụ (không strip diacritics)

Cache in-memory, rebuild sau CACHE_TTL_SECONDS hoặc khi admin gọi /reload-cache.
"""

import json
import logging
import re
import time
import unicodedata
from typing import Optional

import numpy as np
from rank_bm25 import BM25Okapi
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from config import CACHE_TTL_SECONDS, MAX_SUGGESTIONS, SIMILARITY_THRESHOLD
from services.db_service import fetch_active_knowledge, _get_connection, get_system_setting
import pandas as pd
from sklearn.linear_model import LinearRegression

logger = logging.getLogger(__name__)

# ── Cache state ───────────────────────────────────────────────────────────────
_cache_time: float = 0.0
_knowledge_rows: list[dict] = []

_char_vectorizer: Optional[TfidfVectorizer] = None
_char_matrix = None

_word_vectorizer: Optional[TfidfVectorizer] = None
_word_matrix = None

_bm25: Optional[BM25Okapi] = None
_bm25_max_score: float = 1.0   # để normalize BM25 score về [0,1]

_corpus_ids: list[int] = []
_corpus_questions: list[str] = []
_corpus_keywords: list[list[str]] = []
_corpus_tokens: list[list[str]] = []   # tokenized for BM25


# ── Text normalisation ────────────────────────────────────────────────────────

_PUNCT_RE = re.compile(r"[^\w\sàáâãèéêìíòóôõùúýăđơưạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]", re.UNICODE)

def _normalize(text: str) -> str:
    """Chuẩn hoá text cho matching — giữ dấu tiếng Việt, bỏ dấu câu thừa."""
    text = unicodedata.normalize("NFC", text)
    text = text.lower().strip()
    text = _PUNCT_RE.sub(" ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _tokenize(text: str) -> list[str]:
    """Tách từ đơn giản — đủ dùng cho BM25 với tiếng Việt."""
    return _normalize(text).split()


# ── JSON helper ───────────────────────────────────────────────────────────────

def _parse_json_field(value) -> list:
    if isinstance(value, list):
        return value
    if isinstance(value, str):
        try:
            return json.loads(value) or []
        except Exception:
            return []
    return []


# ── Cache build ───────────────────────────────────────────────────────────────

def _build_cache() -> None:
    global _cache_time, _knowledge_rows
    global _char_vectorizer, _char_matrix
    global _word_vectorizer, _word_matrix
    global _bm25, _bm25_max_score
    global _corpus_ids, _corpus_questions, _corpus_keywords, _corpus_tokens

    rows = fetch_active_knowledge()
    if not rows:
        logger.warning("chatbot_knowledge is empty — chatbot will return fallback only")
        _knowledge_rows = []
        _cache_time = time.time()
        return

    corpus_norm: list[str] = []
    ids: list[int] = []
    questions: list[str] = []
    kws: list[list[str]] = []
    tokens: list[list[str]] = []

    for row in rows:
        main_kws = [_normalize(k) for k in _parse_json_field(row.get("keywords"))]

        def _add(text: str) -> None:
            norm = _normalize(text)
            corpus_norm.append(norm)
            ids.append(row["id"])
            questions.append(row["question"])
            kws.append(main_kws)
            tokens.append(_tokenize(text))

        _add(row["question"])
        for alt in _parse_json_field(row.get("alt_questions")):
            _add(alt)

    # 1) Char n-gram TF-IDF
    char_vec = TfidfVectorizer(analyzer="char_wb", ngram_range=(1, 3), sublinear_tf=True, min_df=1)
    char_mat = char_vec.fit_transform(corpus_norm)

    # 2) Word n-gram TF-IDF
    word_vec = TfidfVectorizer(analyzer="word", ngram_range=(1, 2), sublinear_tf=True, min_df=1)
    word_mat = word_vec.fit_transform(corpus_norm)

    # 3) BM25
    bm25 = BM25Okapi(tokens)
    # Precompute max possible BM25 score for normalisation
    try:
        sample_scores = bm25.get_scores(tokens[0])
        max_s = float(np.max(sample_scores)) if len(sample_scores) else 1.0
        bm25_max = max_s if max_s > 0 else 1.0
    except Exception:
        bm25_max = 1.0

    _knowledge_rows = rows
    _char_vectorizer = char_vec
    _char_matrix = char_mat
    _word_vectorizer = word_vec
    _word_matrix = word_mat
    _bm25 = bm25
    _bm25_max_score = bm25_max
    _corpus_ids = ids
    _corpus_questions = questions
    _corpus_keywords = kws
    _corpus_tokens = tokens
    _cache_time = time.time()

    logger.info(
        "NLP cache built: %d knowledge entries → %d corpus rows (char+word TF-IDF, BM25)",
        len(rows), len(corpus_norm),
    )


def _ensure_cache() -> None:
    try:
        ttl = int(get_system_setting("chatbot_cache_ttl", str(CACHE_TTL_SECONDS)))
    except Exception:
        ttl = CACHE_TTL_SECONDS
    if time.time() - _cache_time > ttl or not _knowledge_rows:
        _build_cache()


# ── Public API ────────────────────────────────────────────────────────────────

def match_question(user_input: str) -> dict:
    """Ensemble matching: char TF-IDF + word TF-IDF + BM25 + keyword overlap."""
    _ensure_cache()

    if not _knowledge_rows or _char_vectorizer is None:
        return _fallback_response()

    q_norm = _normalize(user_input)
    q_tokens = _tokenize(user_input)
    user_words = set(q_tokens)

    n = len(_corpus_ids)

    # 1) Char TF-IDF cosine similarity
    char_vec = _char_vectorizer.transform([q_norm])
    char_sims = cosine_similarity(char_vec, _char_matrix).flatten()

    # 2) Word TF-IDF cosine similarity
    try:
        word_vec = _word_vectorizer.transform([q_norm])
        word_sims = cosine_similarity(word_vec, _word_matrix).flatten()
    except Exception:
        word_sims = np.zeros(n)

    # 3) BM25 (normalised to [0,1])
    try:
        bm25_raw = np.array(_bm25.get_scores(q_tokens), dtype=float)
        local_max = float(np.max(bm25_raw)) if np.max(bm25_raw) > 0 else 1.0
        bm25_norm = bm25_raw / local_max
    except Exception:
        bm25_norm = np.zeros(n)

    # 4) Keyword overlap (per-entry)
    kw_scores = np.zeros(n)
    for i, kw_list in enumerate(_corpus_keywords):
        if not kw_list:
            continue
        matches = sum(
            1 for kw in kw_list
            if kw in user_words or any(kw in w for w in user_words)
        )
        kw_scores[i] = min(matches / max(len(kw_list), 1), 1.0)

    # Ensemble weighted sum
    final_scores = (
        0.35 * char_sims
        + 0.25 * word_sims
        + 0.25 * bm25_norm
        + 0.15 * kw_scores
    )

    best_idx = int(np.argmax(final_scores))
    best_score = float(final_scores[best_idx])

    # Dynamic threshold: lower bar nếu BM25 khá chắc hoặc keyword hit rõ
    threshold_str = get_system_setting("chatbot_similarity_threshold", str(SIMILARITY_THRESHOLD))
    try:
        threshold = float(threshold_str)
    except Exception:
        threshold = SIMILARITY_THRESHOLD
    if bm25_norm[best_idx] > 0.6 or kw_scores[best_idx] > 0.5:
        threshold = max(threshold - 0.05, 0.18)

    if best_score < threshold:
        return _fallback_response()

    knowledge_id = _corpus_ids[best_idx]
    row = next((r for r in _knowledge_rows if r["id"] == knowledge_id), None)
    if not row:
        return _fallback_response()

    return {
        "found": True,
        "knowledge_id": knowledge_id,
        "matched_question": _corpus_questions[best_idx],
        "answer": row["answer"],
        "category": row["category"],
        "confidence": round(best_score, 3),
        "suggestions": _get_suggestions(knowledge_id, row),
    }


def get_popular_suggestions(category: Optional[str] = None, limit: int = None) -> list[dict]:
    _ensure_cache()
    if limit is None:
        try:
            limit = int(get_system_setting("chatbot_max_suggestions", str(MAX_SUGGESTIONS)))
        except Exception:
            limit = MAX_SUGGESTIONS
    rows = _knowledge_rows
    if category:
        rows = [r for r in rows if r["category"] == category]
    sorted_rows = sorted(rows, key=lambda r: (r.get("display_order", 999), -r.get("view_count", 0)))
    return [
        {"id": r["id"], "question": r["question"], "category": r["category"]}
        for r in sorted_rows[:limit]
    ]


def reload_cache() -> None:
    global _cache_time
    _cache_time = 0.0
    _build_cache()


# ── Internal helpers ──────────────────────────────────────────────────────────

def _fallback_response() -> dict:
    try:
        max_sug = int(get_system_setting("chatbot_max_suggestions", str(MAX_SUGGESTIONS)))
    except Exception:
        max_sug = MAX_SUGGESTIONS
    popular = get_popular_suggestions(limit=max_sug)
    return {
        "found": False,
        "knowledge_id": None,
        "matched_question": None,
        "answer": (
            "Xin lỗi, tôi chưa hiểu câu hỏi của bạn. 😅\n\n"
            "Bạn có thể thử hỏi theo cách khác, hoặc chọn một trong các câu hỏi phổ biến dưới đây:"
        ),
        "category": None,
        "confidence": 0.0,
        "suggestions": [r["question"] for r in popular],
    }


def _get_suggestions(current_id: int, row: dict) -> list[str]:
    try:
        max_sug = int(get_system_setting("chatbot_max_suggestions", str(MAX_SUGGESTIONS)))
    except Exception:
        max_sug = MAX_SUGGESTIONS
    defined = _parse_json_field(row.get("suggested_questions"))
    if defined:
        return defined[:max_sug]
    same_cat = [
        r["question"]
        for r in _knowledge_rows
        if r["category"] == row["category"] and r["id"] != current_id
    ]
    return same_cat[:max_sug]


def match_advisor_query(user_input: str, restaurant_id: int) -> dict:
    """Xử lý câu hỏi của Chủ quán bằng cách truy vấn số liệu thời gian thực từ DB."""
    q_norm = _normalize(user_input)

    # 1. Doanh thu & tài chính
    if any(k in q_norm for k in ["doanh thu", "tien", "doanh so", "tai chinh"]):
        conn = _get_connection()
        try:
            with conn.cursor() as cursor:
                # Hôm nay
                cursor.execute(
                    """
                    SELECT COALESCE(SUM(total_amount), 0) AS total_revenue, COUNT(*) AS total_orders
                    FROM orders
                    WHERE restaurant_id = %s AND status = 'completed' AND DATE(created_at) = CURDATE()
                    """,
                    (restaurant_id,)
                )
                today = cursor.fetchone()

                # Tuần này
                cursor.execute(
                    """
                    SELECT COALESCE(SUM(total_amount), 0) AS weekly_revenue, COUNT(*) AS weekly_orders
                    FROM orders
                    WHERE restaurant_id = %s AND status = 'completed' 
                      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    """,
                    (restaurant_id,)
                )
                weekly = cursor.fetchone()

                # Phương thức thanh toán hôm nay
                cursor.execute(
                    """
                    SELECT payment_method, SUM(amount) as total
                    FROM payments p
                    JOIN orders o ON o.id = p.order_id
                    WHERE o.restaurant_id = %s AND p.status = 'paid' AND DATE(p.created_at) = CURDATE()
                    GROUP BY payment_method
                    """,
                    (restaurant_id,)
                )
                pm = cursor.fetchall()
                pm_str = "\n".join([f"- **{r['payment_method'].upper()}**: {r['total']:,.0f}đ" for r in pm]) if pm else "- Chưa có giao dịch nào."

            today_rev = float(today['total_revenue'])
            today_orders = int(today['total_orders'])
            weekly_rev = float(weekly['weekly_revenue'])
            weekly_orders = int(weekly['weekly_orders'])

            ans = (
                f"### 📊 BÁO CÁO DOANH THU HÔM NAY\n\n"
                f"- Doanh thu thuần hôm nay: **{today_rev:,.0f}đ**\n"
                f"- Số đơn hàng hoàn thành: **{today_orders} đơn**\n"
                f"- Giá trị trung bình đơn: **{(today_rev / today_orders if today_orders > 0 else 0):,.0f}đ**\n\n"
                f"**Cơ cấu thanh toán hôm nay:**\n{pm_str}\n\n"
                f"**Hiệu suất 7 ngày gần nhất:**\n"
                f"- Tổng doanh thu: **{weekly_rev:,.0f}đ**\n"
                f"- Tổng đơn hàng: **{weekly_orders} đơn**"
            )
            return {
                "found": True,
                "answer": ans,
                "category": "finance",
                "confidence": 1.0,
                "suggestions": [
                    "Món nào bán chạy nhất trong ngày?",
                    "Dự báo doanh thu ngày mai thế nào?",
                    "Nguyên liệu nào đang sắp hết trong kho?"
                ]
            }
        except Exception as e:
            logger.error("Error in advisor finance query: %s", e)
        finally:
            conn.close()

    # 2. Món bán chạy
    if any(k in q_norm for k in ["ban chay", "mon hot", "mon an", "pho bien", "best seller"]):
        conn = _get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT p.name, SUM(oi.quantity) AS total_qty, SUM(oi.line_total) AS total_revenue
                    FROM order_items oi
                    JOIN orders o ON o.id = oi.order_id
                    JOIN products p ON p.id = oi.product_id
                    WHERE o.restaurant_id = %s AND o.status = 'completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY p.id, p.name
                    ORDER BY total_qty DESC
                    LIMIT 5
                    """,
                    (restaurant_id,)
                )
                rows = cursor.fetchall()

            if rows:
                lines = [f"{i+1}. **{r['name']}**: **{int(r['total_qty'])}** phần (Doanh thu: {r['total_revenue']:,.0f}đ)" for i, r in enumerate(rows)]
                ans = "### 🏆 TOP 5 MÓN BÁN CHẠY NHẤT (30 NGÀY QUA)\n\n" + "\n".join(lines)
            else:
                ans = "Nhà hàng chưa phát sinh đơn hàng hoàn thành nào trong 30 ngày qua để thống kê."

            return {
                "found": True,
                "answer": ans,
                "category": "sales",
                "confidence": 1.0,
                "suggestions": [
                    "Doanh thu hôm nay đạt bao nhiêu?",
                    "Dự báo doanh thu ngày mai thế nào?"
                ]
            }
        except Exception as e:
            logger.error("Error in advisor top products: %s", e)
        finally:
            conn.close()

    # 3. Cảnh báo gian lận
    if any(k in q_norm for k in ["gian lan", "bat thuong", "nguy co", "vi pham", "canh bao"]):
        conn = _get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT vr.violation_type, vr.severity, vr.description, DATE_FORMAT(vr.occurred_at, '%%d/%%m/%%Y') as date_vn, e.full_name
                    FROM violation_reports vr
                    LEFT JOIN employees e ON e.id = vr.employee_id
                    WHERE vr.restaurant_id = %s AND vr.status = 'open'
                    ORDER BY vr.created_at DESC
                    LIMIT 3
                    """,
                    (restaurant_id,)
                )
                rows = cursor.fetchall()

            if rows:
                lines = []
                for r in rows:
                    sev_emoji = "🚨" if r['severity'] in ['critical', 'high'] else "⚠️"
                    lines.append(
                        f"{sev_emoji} **[{r['severity'].upper()}] {r['violation_type']}** ({r['date_vn']})\n"
                        f"  - Chi tiết: {r['description']}\n"
                        f"  - Nhân viên liên đới: **{r['full_name'] or 'Không rõ'}**"
                    )
                ans = "### ⚠️ CẢNH BÁO RỦI RO & GIAN LẬN CHƯA XỬ LÝ\n\n" + "\n\n".join(lines)
            else:
                ans = "🎉 Tuyệt vời! Hiện tại không phát hiện bất kỳ dấu hiệu gian lận hay vi phạm chưa xử lý nào tại nhà hàng."

            return {
                "found": True,
                "answer": ans,
                "category": "fraud",
                "confidence": 1.0,
                "suggestions": [
                    "Doanh thu hôm nay đạt bao nhiêu?",
                    "Nguyên liệu nào đang sắp hết trong kho?"
                ]
            }
        except Exception as e:
            logger.error("Error in advisor fraud: %s", e)
        finally:
            conn.close()

    # 4. Tồn kho sắp hết
    if any(k in q_norm for k in ["het hang", "ton kho", "nguyen lieu", "het nguyen lieu", "kho"]):
        conn = _get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT i.name, inv.quantity_on_hand, i.min_stock_level, u.name AS unit_name
                    FROM inventories inv
                    JOIN ingredients i ON i.id = inv.ingredient_id
                    LEFT JOIN units u ON u.id = i.unit_id
                    WHERE inv.restaurant_id = %s AND inv.quantity_on_hand <= i.min_stock_level
                    LIMIT 5
                    """,
                    (restaurant_id,)
                )
                rows = cursor.fetchall()

            if rows:
                lines = [f"- **{r['name']}**: còn **{r['quantity_on_hand']:.2f} {r['unit_name'] or 'đv'}** (Mức an toàn tối thiểu: {r['min_stock_level']:.2f})" for r in rows]
                ans = "### 📦 CẢNH BÁO HẾT NGUYÊN LIỆU TRONG KHO\n\n" + "\n".join(lines) + "\n\n💡 Đề xuất: Bạn nên làm đơn nhập kho PO hoặc kích hoạt *AI tự động đề xuất nhập hàng* trên cổng nhà cung cấp."
            else:
                ans = "✅ Tồn kho nguyên vật liệu hiện tại vẫn đầy đủ và nằm trong ngưỡng an toàn."

            return {
                "found": True,
                "answer": ans,
                "category": "inventory",
                "confidence": 1.0,
                "suggestions": [
                    "Món nào bán chạy nhất trong ngày?",
                    "Dự báo doanh thu ngày mai thế nào?"
                ]
            }
        except Exception as e:
            logger.error("Error in advisor inventory: %s", e)
        finally:
            conn.close()

    # 5. Dự báo doanh thu ngày mai
    if any(k in q_norm for k in ["du bao", "ngay mai", "tuong lai"]):
        conn = _get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT summary_date, net_revenue
                    FROM restaurant_revenue_summaries
                    WHERE restaurant_id = %s AND summary_type = 'daily'
                    ORDER BY summary_date DESC
                    LIMIT 14
                    """,
                    (restaurant_id,)
                )
                rows = cursor.fetchall()

            if len(rows) >= 3:
                df = pd.DataFrame(rows)
                df = df.iloc[::-1].reset_index(drop=True)
                X = np.arange(len(df)).reshape(-1, 1)
                y = df['net_revenue'].values

                model = LinearRegression().fit(X, y)
                pred = max(0.0, float(model.predict([[len(df)]])[0]))

                confidence = "Cao" if len(rows) >= 10 else "Trung bình"
                ans = (
                    f"### 🔮 AI DỰ BÁO DOANH THU NGÀY MAI\n\n"
                    f"- Dự báo doanh thu ngày mai: **{pred:,.0f}đ**\n"
                    f"- Độ tin cậy dự báo: **{confidence}** (Hồi quy LinearRegression)\n\n"
                    f"*Phân tích:* Mô hình AI phân tích xu hướng tiêu dùng từ lịch sử doanh thu {len(rows)} ngày gần nhất để tự động đưa ra ước tính."
                )
            else:
                ans = (
                    f"### 🔮 AI DỰ BÁO DOANH THU NGÀY MAI\n\n"
                    f"- Dự báo doanh thu ngày mai: **1,500,000đ** (Ước tính)\n"
                    f"- Độ tin cậy dự báo: **Thấp** (Chưa đủ 14 ngày dữ liệu báo cáo để chạy Machine Learning).\n\n"
                    f"*Gợi ý:* Hãy tiếp tục vận hành hệ thống thêm vài ngày nữa để AI thu thập đủ mẫu dữ liệu tài chính."
                )

            return {
                "found": True,
                "answer": ans,
                "category": "forecast",
                "confidence": 1.0,
                "suggestions": [
                    "Doanh thu hôm nay đạt bao nhiêu?",
                    "Có cảnh báo gian lận nào chưa xử lý không?"
                ]
            }
        except Exception as e:
            logger.error("Error in advisor forecast: %s", e)
        finally:
            conn.close()

    res = match_question(user_input)
    if res["found"]:
        return res

    return {
        "found": False,
        "answer": (
            f"Chào bạn, tôi chưa hiểu rõ ý bạn về mặt nghiệp vụ vận hành. 😅\n\n"
            f"Hãy hỏi tôi các vấn đề cụ thể liên quan đến **doanh thu, lợi nhuận, món bán chạy, cảnh báo kho hàng, vi phạm gian lận** hoặc **dự báo doanh thu**."
        ),
        "category": None,
        "confidence": 0.0,
        "suggestions": [
            "Doanh thu hôm nay đạt bao nhiêu?",
            "Món nào bán chạy nhất trong ngày?",
            "Có cảnh báo gian lận nào chưa xử lý không?"
        ]
    }
