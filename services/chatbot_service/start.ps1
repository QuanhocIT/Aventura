# Chatbot Service Launcher — kills any existing port 8002 process then starts uvicorn
$ErrorActionPreference = 'SilentlyContinue'

$pidLine = netstat -ano | Select-String ":8002\s" | Select-Object -First 1
if ($pidLine) {
    $pid = ($pidLine -split '\s+') | Where-Object { $_ -match '^\d+$' } | Select-Object -Last 1
    if ($pid) {
        Stop-Process -Id $pid -Force
        Write-Host "Stopped existing process $pid on port 8002"
    }
}

$pythonPath = "C:\laragon\bin\python\python-3.10\python.exe"
$serviceDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $serviceDir

Write-Host "Starting chatbot service at http://0.0.0.0:8002 ..."
& $pythonPath -m uvicorn main:app --host 0.0.0.0 --port 8002 --reload
