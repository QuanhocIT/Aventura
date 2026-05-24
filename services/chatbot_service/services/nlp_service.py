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
from services.db_service import fetch_active_knowledge

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
    if time.time() - _cache_time > CACHE_TTL_SECONDS or not _knowledge_rows:
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
    threshold = SIMILARITY_THRESHOLD
    if bm25_norm[best_idx] > 0.6 or kw_scores[best_idx] > 0.5:
        threshold = max(SIMILARITY_THRESHOLD - 0.05, 0.18)

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


def get_popular_suggestions(category: Optional[str] = None, limit: int = MAX_SUGGESTIONS) -> list[dict]:
    _ensure_cache()
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
    popular = get_popular_suggestions(limit=MAX_SUGGESTIONS)
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
    defined = _parse_json_field(row.get("suggested_questions"))
    if defined:
        return defined[:MAX_SUGGESTIONS]
    same_cat = [
        r["question"]
        for r in _knowledge_rows
        if r["category"] == row["category"] and r["id"] != current_id
    ]
    return same_cat[:MAX_SUGGESTIONS]
