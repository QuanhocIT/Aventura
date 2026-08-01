# Khởi động cả 3 AI service Python (email:8001, chatbot:8002, analytics:8003) bằng 1 lệnh.
#
#   powershell -ExecutionPolicy Bypass -File services\start-all.ps1
#
# Mỗi service chạy trong 1 cửa sổ PowerShell riêng (đóng cửa sổ = tắt service).
# Nếu port đang bận thì kill process cũ trước để tránh lỗi "address already in use".
#
# Lưu ý Windows: uvicorn bind IPv4 (0.0.0.0) — mọi client PHP phải gọi 127.0.0.1
# chứ KHÔNG dùng "localhost" (resolve ::1 trước, treo 2s rồi báo offline).

$ErrorActionPreference = 'Stop'

$servicesDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# Ưu tiên venv nếu có, không thì dùng python hệ thống
function Resolve-Python([string] $serviceDir) {
    $venvPython = Join-Path $serviceDir 'venv\Scripts\python.exe'
    if (Test-Path $venvPython) { return $venvPython }
    return 'python'
}

$services = @(
    @{ Name = 'email_service';     Port = 8001 },
    @{ Name = 'chatbot_service';   Port = 8002 },
    @{ Name = 'analytics_service'; Port = 8003 }
)

foreach ($svc in $services) {
    $port = $svc.Port
    $dir = Join-Path $servicesDir $svc.Name

    if (-not (Test-Path (Join-Path $dir 'main.py'))) {
        Write-Host "[BO QUA] $($svc.Name): khong tim thay main.py" -ForegroundColor Yellow
        continue
    }

    # Kill process cũ đang giữ port (nếu có)
    $existing = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($existing) {
        try { Stop-Process -Id $existing.OwningProcess -Force -Confirm:$false } catch {}
        Write-Host "[RESTART] $($svc.Name): da dung process cu tren port $port"
    }

    $python = Resolve-Python $dir

    Start-Process powershell -WorkingDirectory $dir -ArgumentList @(
        '-NoExit',
        '-Command',
        "& '$python' -m uvicorn main:app --host 0.0.0.0 --port $port"
    ) | Out-Null

    Write-Host "[OK] $($svc.Name) -> http://127.0.0.1:$port" -ForegroundColor Green
}

Write-Host ''
Write-Host 'Ca 3 AI service dang khoi dong. Kiem tra nhanh:' -ForegroundColor Cyan
Write-Host '  curl http://127.0.0.1:8001/  (email)'
Write-Host '  curl http://127.0.0.1:8002/  (chatbot)'
Write-Host '  curl http://127.0.0.1:8003/  (analytics)'
