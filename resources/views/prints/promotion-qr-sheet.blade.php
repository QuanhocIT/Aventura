<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>QR Khuyến Mãi — {{ $restaurant }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 20px; background: #fff; }
        h1 { text-align: center; font-size: 18px; margin-bottom: 20px; color: #333; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card { border: 2px dashed #ddd; border-radius: 12px; padding: 16px; text-align: center; page-break-inside: avoid; }
        .card svg { width: 160px; height: 160px; margin: 0 auto 8px; display: block; }
        .card .name { font-size: 12px; font-weight: 700; color: #333; margin-bottom: 4px; }
        .card .code { font-size: 14px; font-weight: 900; color: #e65100; letter-spacing: 2px; font-family: monospace; }
        .card .discount { font-size: 20px; font-weight: 900; color: #2e7d32; margin-top: 4px; }
        .card .hint { font-size: 9px; color: #999; margin-top: 6px; }
        @media print { body { padding: 10px; } .grid { gap: 12px; } }
    </style>
</head>
<body>
    <h1>🎟️ Mã Khuyến Mãi — {{ $restaurant }}</h1>
    <div class="grid">
        @foreach($qrCodes as $qr)
            <div class="card">
                {!! $qr['svg'] !!}
                <div class="name">{{ $qr['name'] }}</div>
                <div class="code">{{ $qr['code'] }}</div>
                <div class="discount">Giảm {{ $qr['discount'] }}</div>
                <div class="hint">Quét QR để nhận mã giảm giá</div>
            </div>
        @endforeach
    </div>
    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
