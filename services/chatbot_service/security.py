import os
from dotenv import load_dotenv
from fastapi import Header, HTTPException, status

load_dotenv()

INTERNAL_KEY: str = os.getenv("MICROSERVICE_INTERNAL_KEY", "")


async def verify_internal_key(x_internal_key: str = Header(default="")):
    """Rejects calls that don't carry Laravel's shared secret — this service has
    no other authentication and would otherwise accept requests from anyone who
    can reach its port."""
    if not INTERNAL_KEY or x_internal_key != INTERNAL_KEY:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Unauthorized")
