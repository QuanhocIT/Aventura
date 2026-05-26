from dotenv import load_dotenv
import os

load_dotenv()

DB_HOST: str = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT: int = int(os.getenv("DB_PORT", "3306"))
DB_DATABASE: str = os.getenv("DB_DATABASE", "aventura")
DB_USERNAME: str = os.getenv("DB_USERNAME", "root")
DB_PASSWORD: str = os.getenv("DB_PASSWORD", "")

CACHE_TTL_SECONDS: int = int(os.getenv("CACHE_TTL_SECONDS", "300"))
SIMILARITY_THRESHOLD: float = float(os.getenv("SIMILARITY_THRESHOLD", "0.28"))
MAX_SUGGESTIONS: int = int(os.getenv("MAX_SUGGESTIONS", "5"))
