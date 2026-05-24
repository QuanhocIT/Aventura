import brevo
from jinja2 import Environment, FileSystemLoader
from pathlib import Path
import logging

from config import BREVO_API_KEY, EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME

logger = logging.getLogger(__name__)

TEMPLATES_DIR = Path(__file__).parent.parent / "templates"
jinja_env = Environment(loader=FileSystemLoader(str(TEMPLATES_DIR)), autoescape=True)


def _get_client() -> brevo.Brevo:
    return brevo.Brevo(api_key=BREVO_API_KEY)


def _render(template_name: str, context: dict) -> str:
    return jinja_env.get_template(template_name).render(**context)


def send_welcome_email(name: str, email: str, restaurant_name: str, trial_days: int, login_url: str) -> str:
    html_content = _render("welcome.html", {
        "name": name,
        "restaurant_name": restaurant_name,
        "trial_days": trial_days,
        "login_url": login_url,
    })

    client = _get_client()
    response = client.transactional_emails.send_transac_email(
        to=[brevo.SendTransacEmailRequestToItem(email=email, name=name)],
        sender=brevo.SendTransacEmailRequestSender(email=EMAIL_FROM_ADDRESS, name=EMAIL_FROM_NAME),
        subject=f"Chào mừng {name} đến với Aventura!",
        html_content=html_content,
    )
    logger.info("Welcome email sent to %s, messageId=%s", email, response.message_id)
    return response.message_id or ""


def send_invoice_email(
    recipient_email: str,
    invoice_number: str,
    restaurant_name: str,
    plan_name: str,
    amount: float,
    currency: str,
    issued_on: str,
    due_on: str,
    pdf_url: str | None,
) -> str:
    html_content = _render("invoice.html", {
        "invoice_number": invoice_number,
        "restaurant_name": restaurant_name,
        "plan_name": plan_name,
        "amount": f"{amount:,.0f}",
        "currency": currency,
        "issued_on": issued_on,
        "due_on": due_on,
        "pdf_url": pdf_url,
    })

    client = _get_client()
    response = client.transactional_emails.send_transac_email(
        to=[brevo.SendTransacEmailRequestToItem(email=recipient_email)],
        sender=brevo.SendTransacEmailRequestSender(email=EMAIL_FROM_ADDRESS, name=EMAIL_FROM_NAME),
        subject=f"Hóa đơn #{invoice_number} - Aventura Subscription",
        html_content=html_content,
    )
    logger.info("Invoice email sent to %s, invoice=%s, messageId=%s", recipient_email, invoice_number, response.message_id)
    return response.message_id or ""
