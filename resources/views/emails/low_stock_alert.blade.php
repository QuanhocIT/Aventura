<div style='font-family:sans-serif;max-width:600px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:2px solid #fca5a5'>
    <h2 style='color:#dc2626'>🚨 Cảnh báo tồn kho thấp</h2>
    <p>Nhà hàng <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong> có <strong>{{ count($items ?? []) }} nguyên liệu</strong> cần đặt hàng gấp:</p>
    <table style='width:100%;border-collapse:collapse;margin:16px 0'>
        <thead>
            <tr style='background:#fef2f2'>
                <th style='padding:10px;text-align:left;border:1px solid #fecaca;color:#991b1b'>Nguyên liệu</th>
                <th style='padding:10px;text-align:right;border:1px solid #fecaca;color:#991b1b'>Còn lại</th>
                <th style='padding:10px;text-align:right;border:1px solid #fecaca;color:#991b1b'>Mức tối thiểu</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items ?? [] as $item)
                <tr>
                    <td style='padding:8px 10px;border:1px solid #fee2e2'>{{ $item['name'] ?? '' }}</td>
                    <td style='padding:8px 10px;border:1px solid #fee2e2;text-align:right;color:#dc2626;font-weight:bold'>{{ $item['current'] ?? '' }} {{ $item['unit'] ?? '' }}</td>
                    <td style='padding:8px 10px;border:1px solid #fee2e2;text-align:right;color:#6b7280'>{{ $item['min'] ?? '' }} {{ $item['unit'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <a href='{{ $inventory_url ?? '#' }}' style='display:inline-block;background:#dc2626;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:8px'>Vào quản lý kho →</a>
    <p style='color:#9ca3af;font-size:12px;margin-top:16px'>Cảnh báo tự động từ hệ thống Aventura · {{ now()->format('d/m/Y H:i') }}</p>
</div>
