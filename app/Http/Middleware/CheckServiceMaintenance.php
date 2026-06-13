<?php

namespace App\Http\Middleware;

use App\Services\ServiceMonitorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckServiceMaintenance
{
    public function __construct(private ServiceMonitorService $monitorService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass checks for super-admin or public status routes
        if ($request->is('super-admin*') || $request->is('status') || $request->is('api/status-data') || $request->is('impersonate*')) {
            return $next($request);
        }

        // Check if MySQL database is under maintenance
        if ($this->monitorService->isMaintenance('mysql')) {
            $message = $this->monitorService->getMaintenanceMessage('mysql') 
                ?: 'Hệ thống cơ sở dữ liệu MySQL đang được bảo trì nâng cấp. Vui lòng quay lại sau.';

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                    'under_maintenance' => true,
                ], 503);
            }

            // Fallback to error view
            if (view()->exists('errors.mysql-maintenance')) {
                return response()->view('errors.mysql-maintenance', ['message' => $message], 503);
            }

            // Fallback inline styling if view doesn't render
            return response($this->getFallbackHtml($message), 503);
        }

        return $next($request);
    }

    /**
     * Fallback HTML layout in case blade view is missing or errors out.
     */
    private function getFallbackHtml(string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống đang bảo trì - Aventura</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at center, #111827 0%, #030712 100%);
            color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .icon-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.3);
            border-radius: 50%;
            margin-bottom: 24px;
            position: relative;
        }
        .icon-box::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid rgba(239, 68, 68, 0.5);
            animation: pulse 2s infinite;
        }
        .icon {
            font-size: 32px;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 16px 0;
            background: linear-gradient(to right, #f3f4f6, #9ca3af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #9ca3af;
            margin: 0 0 28px 0;
        }
        .status-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background: #4f46e5;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .status-btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.3); opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-box">
            <span class="icon">🛠️</span>
        </div>
        <h1>Hệ Thống Đang Bảo Trì</h1>
        <p>{$message}</p>
        <a href="/status" class="status-btn">Xem Trạng Thái Hệ Thống</a>
    </div>
</body>
</html>
HTML;
    }
}
