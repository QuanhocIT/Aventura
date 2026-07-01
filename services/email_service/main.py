import logging
from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel
from typing import Any

from models import WelcomeEmailRequest, InvoiceEmailRequest, OtpEmailRequest, VerificationEmailRequest, EmailResponse, CampaignEmailRequest
from services.brevo_service import send_welcome_email, send_invoice_email, send_otp_email, send_verification_email, send_campaign_email
from services.ai_service import analyze
from config import BREVO_API_KEY
from security import verify_internal_key

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Aventura Microservice",
    description="Email & AI Python microservice cho Aventura SaaS",
    version="1.1.0",
    dependencies=[Depends(verify_internal_key)],
)


class AiInsightsRequest(BaseModel):
    restaurants: list[dict[str, Any]]
    tenant_growth: list[dict[str, Any]]


@app.get("/health")
def health_check():
    has_key = bool(BREVO_API_KEY)
    return {"status": "ok", "brevo_configured": has_key}


@app.post("/ai/insights")
def ai_insights(payload: AiInsightsRequest):
    try:
        result = analyze(payload.restaurants, payload.tenant_growth)
        return result
    except Exception as e:
        logger.error("AI insights error: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/send/welcome", response_model=EmailResponse)
def send_welcome(payload: WelcomeEmailRequest):
    if not BREVO_API_KEY:
        raise HTTPException(status_code=500, detail="BREVO_API_KEY chưa được cấu hình")
    try:
        message_id = send_welcome_email(
            name=payload.name,
            email=payload.email,
            restaurant_name=payload.restaurant_name,
            trial_days=payload.trial_days,
            login_url=payload.login_url,
        )
        return EmailResponse(success=True, message="Welcome email đã được gửi", message_id=message_id)
    except Exception as e:
        logger.error("Error sending welcome email: %s", e)
        raise HTTPException(status_code=502, detail=str(e))


@app.post("/send/otp", response_model=EmailResponse)
def send_otp(payload: OtpEmailRequest):
    if not BREVO_API_KEY:
        raise HTTPException(status_code=500, detail="BREVO_API_KEY chưa được cấu hình")
    try:
        message_id = send_otp_email(
            recipient_email=payload.recipient_email,
            code=payload.code,
            expires_in_minutes=payload.expires_in_minutes,
            recipient_name=payload.recipient_name,
        )
        return EmailResponse(success=True, message="OTP email đã được gửi", message_id=message_id)
    except Exception as e:
        logger.error("Error sending OTP email: %s", e)
        raise HTTPException(status_code=502, detail=str(e))


@app.post("/send/verification", response_model=EmailResponse)
def send_verification(payload: VerificationEmailRequest):
    if not BREVO_API_KEY:
        raise HTTPException(status_code=500, detail="BREVO_API_KEY chưa được cấu hình")
    try:
        message_id = send_verification_email(
            recipient_email=payload.recipient_email,
            verification_url=payload.verification_url,
            code=payload.code,
            code_expires_in_minutes=payload.code_expires_in_minutes,
            link_expires_in_minutes=payload.link_expires_in_minutes,
            recipient_name=payload.recipient_name,
        )
        return EmailResponse(success=True, message="Email xác thực đã được gửi", message_id=message_id)
    except Exception as e:
        logger.error("Error sending verification email: %s", e)
        raise HTTPException(status_code=502, detail=str(e))


@app.post("/send/invoice", response_model=EmailResponse)
def send_invoice(payload: InvoiceEmailRequest):
    if not BREVO_API_KEY:
        raise HTTPException(status_code=500, detail="BREVO_API_KEY chưa được cấu hình")
    try:
        message_id = send_invoice_email(
            recipient_email=payload.recipient_email,
            invoice_number=payload.invoice_number,
            restaurant_name=payload.restaurant_name,
            plan_name=payload.plan_name,
            amount=payload.amount,
            currency=payload.currency,
            issued_on=payload.issued_on,
            due_on=payload.due_on,
            pdf_url=payload.pdf_url,
        )
        return EmailResponse(success=True, message="Invoice email đã được gửi", message_id=message_id)
    except Exception as e:
        logger.error("Error sending invoice email: %s", e)
        raise HTTPException(status_code=502, detail=str(e))


@app.post("/send/campaign", response_model=EmailResponse)
def send_campaign(payload: CampaignEmailRequest):
    if not BREVO_API_KEY:
        raise HTTPException(status_code=500, detail="BREVO_API_KEY chưa được cấu hình")
    try:
        message_id = send_campaign_email(
            recipient_email=payload.recipient_email,
            title=payload.title,
            content=payload.content,
            recipient_name=payload.recipient_name,
        )
        return EmailResponse(success=True, message="Campaign email đã được gửi", message_id=message_id)
    except Exception as e:
        logger.error("Error sending campaign email: %s", e)
        raise HTTPException(status_code=502, detail=str(e))

