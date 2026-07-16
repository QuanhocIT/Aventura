from dotenv import load_dotenv
import os

load_dotenv()

BREVO_API_KEY: str = os.getenv("BREVO_API_KEY", "")
EMAIL_FROM_ADDRESS: str = os.getenv("EMAIL_FROM_ADDRESS", "no-reply@aventura.vn")
EMAIL_FROM_NAME: str = os.getenv("EMAIL_FROM_NAME", "Aventura")
APP_URL: str = os.getenv("APP_URL", "http://localhost:8000")

# Khóa nội bộ xác thực giao tiếp từ Laravel.
# Sinh bằng: openssl rand -hex 32
# Để trống ở môi trường dev (bỏ qua kiểm tra).
INTERNAL_API_KEY: str = os.getenv("INTERNAL_API_KEY", "")

