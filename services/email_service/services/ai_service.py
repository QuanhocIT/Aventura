from __future__ import annotations
import numpy as np
from datetime import datetime
from dateutil.relativedelta import relativedelta


def _churn_score(r: dict) -> tuple[int, list[str], list[str]]:
    score = 0
    reasons: list[str] = []
    actions: list[str] = []

    if r.get("subscription_status") == "expired":
        score += 40
        reasons.append("Subscription đã hết hạn")
        actions.append("Liên hệ ngay để gia hạn — cung cấp ưu đãi giảm giá 1 tháng")

    if r.get("subscription_status") == "cancelled":
        score += 15
        reasons.append("Đã huỷ subscription")
        actions.append("Gửi khảo sát lý do huỷ và đề xuất gói phù hợp hơn")

    if r.get("is_trial") and r.get("days_since_created", 0) > 7:
        score += 35
        reasons.append("Trial > 7 ngày chưa nâng cấp Pro")
        actions.append("Gửi email demo tính năng Pro — nhấn mạnh ROI và tiết kiệm thời gian")

    if r.get("order_count_30d", 0) == 0:
        score += 25
        reasons.append("Không có đơn hàng trong 30 ngày qua")
        actions.append("Kiểm tra xem nhà hàng có đang dùng hệ thống không — gợi ý onboarding lại")

    days_left = r.get("days_until_subscription_ends", -1)
    if 0 < days_left < 7:
        score += 20
        reasons.append(f"Subscription còn {days_left} ngày hết hạn")
        actions.append(f"Gửi nhắc gia hạn khẩn cấp — còn {days_left} ngày")
    elif 7 <= days_left <= 14:
        score += 8
        reasons.append(f"Subscription còn {days_left} ngày hết hạn")
        actions.append("Gửi email nhắc gia hạn sớm với ưu đãi thanh toán trước")

    return min(100, score), reasons, actions


def _risk_level(score: int) -> str:
    if score >= 70:
        return "high"
    if score >= 40:
        return "medium"
    return "low"


def _health_score(churn: int, order_count: int, is_pro: bool, is_trial: bool) -> int:
    base = 100 - churn
    order_bonus = min(15, order_count // 3)
    plan_bonus = 10 if is_pro else (3 if is_trial else 0)
    return min(100, max(0, base + order_bonus + plan_bonus))


def _health_level(score: int) -> str:
    if score >= 75:
        return "good"
    if score >= 45:
        return "fair"
    return "poor"


def _segment(r: dict, churn: int) -> str:
    days = r.get("days_since_created", 999)
    status = r.get("subscription_status", "")
    plan = r.get("plan_code", "free").lower()
    is_trial = r.get("is_trial", False)
    orders = r.get("order_count_30d", 0)

    if status in ("expired", "cancelled"):
        return "churned"
    if days <= 3:
        return "new"
    if churn >= 50:
        return "at_risk"
    if plan == "pro":
        return "active_pro"
    if is_trial and days <= 14:
        return "trial_active"
    return "free_inactive"


def _mrr_forecast(tenant_growth: list[dict]) -> list[dict]:
    if not tenant_growth or len(tenant_growth) < 2:
        return []

    conversions = np.array([p.get("free_to_pro", 0) for p in tenant_growth], dtype=float)
    months_x = np.arange(len(conversions), dtype=float)

    if conversions.sum() == 0:
        slope, intercept = 0.0, 0.0
    else:
        coeffs = np.polyfit(months_x, conversions, 1)
        slope, intercept = float(coeffs[0]), float(coeffs[1])

    PRO_PRICE = 499_000
    result = []
    last_month_str = tenant_growth[-1].get("month", "")
    try:
        last_month = datetime.strptime(last_month_str, "%Y-%m")
    except ValueError:
        return []

    prev_mrr = conversions[-1] * PRO_PRICE
    for i in range(1, 4):
        future_x = len(conversions) - 1 + i
        predicted = max(0.0, slope * future_x + intercept)
        predicted_mrr = round(predicted * PRO_PRICE)
        trend = "up" if predicted_mrr >= prev_mrr else "down"
        forecast_month = last_month + relativedelta(months=i)
        result.append({
            "month": forecast_month.strftime("%m/%Y"),
            "predicted_mrr": predicted_mrr,
            "trend": trend,
        })
        prev_mrr = predicted_mrr

    return result


def _overall_health(restaurants: list[dict], churn_scores: list[int]) -> dict:
    if not restaurants:
        return {"score": 0, "label": "Chưa có dữ liệu", "color": "gray"}

    avg_churn = sum(churn_scores) / len(churn_scores)
    high_risk = sum(1 for s in churn_scores if s >= 70)
    risk_ratio = high_risk / len(churn_scores)

    score = max(0, round(100 - avg_churn - risk_ratio * 20))
    if score >= 75:
        return {"score": score, "label": "Hệ thống khoẻ mạnh", "color": "green"}
    if score >= 50:
        return {"score": score, "label": "Cần chú ý một số tenant", "color": "yellow"}
    return {"score": score, "label": "Nhiều tenant đang gặp rủi ro", "color": "red"}


def analyze(restaurants: list[dict], tenant_growth: list[dict]) -> dict:
    churn_risks = []
    health_scores = []
    all_churn_scores: list[int] = []
    segment_counts = {"active_pro": 0, "trial_active": 0, "free_inactive": 0, "at_risk": 0, "churned": 0, "new": 0}

    for r in restaurants:
        score, reasons, actions = _churn_score(r)
        is_pro = r.get("plan_code", "free").lower() == "pro"
        health = _health_score(score, r.get("order_count_30d", 0), is_pro, r.get("is_trial", False))
        seg = _segment(r, score)
        all_churn_scores.append(score)

        if seg in segment_counts:
            segment_counts[seg] += 1

        churn_risks.append({
            "restaurant_id": r.get("id", 0),
            "name": r.get("name", "Unknown"),
            "risk_score": score,
            "risk_level": _risk_level(score),
            "reasons": reasons,
            "actions": actions,
        })

        health_scores.append({
            "restaurant_id": r.get("id", 0),
            "name": r.get("name", "Unknown"),
            "score": health,
            "level": _health_level(health),
            "order_count_30d": r.get("order_count_30d", 0),
        })

    churn_risks.sort(key=lambda x: x["risk_score"], reverse=True)
    health_scores.sort(key=lambda x: x["score"], reverse=True)

    return {
        "churn_risks": churn_risks[:5],
        "health_scores": health_scores[:5],
        "segments": segment_counts,
        "mrr_forecast": _mrr_forecast(tenant_growth),
        "overall_health": _overall_health(restaurants, all_churn_scores),
    }
