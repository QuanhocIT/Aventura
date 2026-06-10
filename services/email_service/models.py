from pydantic import BaseModel, EmailStr
from typing import Optional


class WelcomeEmailRequest(BaseModel):
    name: str
    email: EmailStr
    restaurant_name: str
    trial_days: int = 14
    login_url: str


class OtpEmailRequest(BaseModel):
    recipient_email: EmailStr
    recipient_name: Optional[str] = None
    code: str
    expires_in_minutes: int = 5


class VerificationEmailRequest(BaseModel):
    recipient_email: EmailStr
    recipient_name: Optional[str] = None
    verification_url: str
    code: str
    code_expires_in_minutes: int = 30
    link_expires_in_minutes: int = 60


class InvoiceEmailRequest(BaseModel):
    recipient_email: EmailStr
    invoice_number: str
    restaurant_name: str
    plan_name: str
    amount: float
    currency: str = "VND"
    issued_on: str
    due_on: str
    pdf_url: Optional[str] = None


class CampaignEmailRequest(BaseModel):
    recipient_email: EmailStr
    recipient_name: Optional[str] = None
    title: str
    content: str


class EmailResponse(BaseModel):
    success: bool
    message: str
    message_id: Optional[str] = None
