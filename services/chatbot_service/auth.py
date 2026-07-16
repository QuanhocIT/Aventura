"""
auth.py — Internal API Key middleware dùng chung cho tất cả Python microservices.

Bảo vệ các endpoint khỏi bị gọi từ bên ngoài (chỉ cho phép Laravel gọi).

Cách dùng trong main.py:
    from auth import require_api_key

    @app.post("/api/some-endpoint", dependencies=[Depends(require_api_key)])
    def some_endpoint(...):
        ...

Cấu hình:
    - Set INTERNAL_API_KEY trong .env (sinh bằng: openssl rand -hex 32)
    - Đặt cùng giá trị trong .env của Laravel (INTERNAL_API_KEY=...)
    - Laravel gửi header: X-Internal-API-Key: {key}

Dev mode:
    - Nếu INTERNAL_API_KEY trống → bỏ qua kiểm tra (cho phép local dev không cần key)
"""

import os
import logging
from fastapi import Depends, HTTPException, Security, status
from fastapi.security import APIKeyHeader

logger = logging.getLogger(__name__)

_INTERNAL_API_KEY = os.getenv("INTERNAL_API_KEY", "")
_api_key_header = APIKeyHeader(name="X-Internal-API-Key", auto_error=False)


async def require_api_key(api_key: str | None = Security(_api_key_header)) -> None:
    """
    FastAPI dependency: yêu cầu header X-Internal-API-Key hợp lệ.

    - Nếu INTERNAL_API_KEY chưa được set (dev): bỏ qua, cho phép tất cả.
    - Nếu đã set (production): từ chối mọi request không có header hoặc sai key.
    """
    if not _INTERNAL_API_KEY:
        # Dev mode — cảnh báo nhưng không chặn
        logger.debug("INTERNAL_API_KEY chưa được cấu hình — đang chạy ở chế độ dev (không kiểm tra auth).")
        return

    if not api_key or api_key != _INTERNAL_API_KEY:
        logger.warning(
            "Unauthorized request: key=%s",
            (api_key[:6] + "...") if api_key else "MISSING",
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Không có quyền truy cập: thiếu hoặc sai X-Internal-API-Key header.",
        )
