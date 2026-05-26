from pydantic import BaseModel, EmailStr
from typing import Optional


class WelcomeEmailRequest(BaseModel):
    name: str
    email: EmailStr
    restaurant_name: str
    trial_days: int = 14
    login_url: str


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


class EmailResponse(BaseModel):
    success: bool
    message: str
    message_id: Optional[str] = None
