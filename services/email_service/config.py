from dotenv import load_dotenv
import os

load_dotenv()

BREVO_API_KEY: str = os.getenv("BREVO_API_KEY", "")
EMAIL_FROM_ADDRESS: str = os.getenv("EMAIL_FROM_ADDRESS", "no-reply@aventura.vn")
EMAIL_FROM_NAME: str = os.getenv("EMAIL_FROM_NAME", "Aventura")
APP_URL: str = os.getenv("APP_URL", "http://localhost:8000")
