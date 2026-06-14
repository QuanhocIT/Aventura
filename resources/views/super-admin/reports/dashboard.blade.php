<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Báo cáo tổng quan hệ thống — {{ $now->format('d/m/Y H:i') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            color: #1f2933;
            margin: 0;
            padding: 32px;
            background: #f8fafc;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }
        .toolbar button {
            background: #4338ca;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
        }
        .sheet {
            max-width: 960px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 32px 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }
        .meta {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 24px;
        }
        h2 {
            font-size: 15px;
            margin: 28px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e2e8f0;
            color: #312e81;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            text-align: left;
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
        }
        td.num, th.num { text-align: right; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .kpi-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .kpi-card .label { font-size: 12px; color: #64748b; }
        .kpi-card .value { font-size: 20px; font-weight: 700; margin-top: 2px; }
        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge.critical { background: #fee2e2; color: #b91c1c; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
            h2 { break-inside: avoid; }
            table { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">In / Lưu thành PDF</button>
    </div>

    <div class="sheet">
        <h1>Báo cáo tổng quan hệ thống Aventura</h1>
        <p class="meta">Xuất lúc {{ $now->format('H:i, d/m/Y') }} · Phạm vi: toàn hệ thống</p>

        <h2>Chỉ số tổng quan</h2>
        <div class="kpi-grid">
            <div class="kpi-card"><div class="label">Tổng số nhà hàng</div><div class="value">{{ number_format($data['stats']['total_restaurants']) }}</div></div>
            <div class="kpi-card"><div class="label">Đang hoạt động</div><div class="value">{{ number_format($data['stats']['active']) }}</div></div>
            <div class="kpi-card"><div class="label">Tạm ngưng / Hết hạn</div><div class="value">{{ number_format($data['stats']['suspended']) }} / {{ number_format($data['stats']['expired']) }}</div></div>
            <div class="kpi-card"><div class="label">Tổng số người dùng</div><div class="value">{{ number_format($data['stats']['total_users']) }}</div></div>
            <div class="kpi-card"><div class="label">Nhà hàng gói Pro</div><div class="value">{{ number_format($data['stats']['pro_plan']) }}</div></div>
            <div class="kpi-card"><div class="label">MRR / ARR (VNĐ)</div><div class="value">{{ number_format($data['saasMetrics']['mrr']) }} / {{ number_format($data['saasMetrics']['arr']) }}</div></div>
        </div>

        <h2>Chỉ số SaaS</h2>
        <table>
            <tbody>
                <tr><td>Tỷ lệ rời bỏ tháng này</td><td class="num">{{ $data['saasMetrics']['churn_rate'] }}%</td></tr>
                <tr><td>Số gói huỷ trong tháng</td><td class="num">{{ number_format($data['saasMetrics']['churned_this_month']) }}</td></tr>
                <tr><td>Số thuê bao đang hoạt động</td><td class="num">{{ number_format($data['saasMetrics']['active_subscriptions']) }}</td></tr>
                <tr><td>Số tenant trả phí</td><td class="num">{{ number_format($data['saasMetrics']['paid_tenants']) }}</td></tr>
            </tbody>
        </table>

        @php($retention = $data['revenueRetention'])
        <h2>Giữ chân doanh thu — {{ $retention['period_label'] }} so với {{ $retention['previous_label'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th class="num">MRR đầu kỳ</th>
                    <th class="num">Mở rộng</th>
                    <th class="num">Co lại</th>
                    <th class="num">Mất đi</th>
                    <th class="num">NRR</th>
                    <th class="num">GRR</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="num">{{ number_format($retention['starting_mrr']) }}</td>
                    <td class="num">{{ number_format($retention['expansion']) }}</td>
                    <td class="num">{{ number_format($retention['contraction']) }}</td>
                    <td class="num">{{ number_format($retention['churned']) }}</td>
                    <td class="num">{{ $retention['nrr'] !== null ? $retention['nrr'].'%' : 'N/A' }}</td>
                    <td class="num">{{ $retention['grr'] !== null ? $retention['grr'].'%' : 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Tăng trưởng tenant theo tháng (6 tháng gần nhất)</h2>
        <table>
            <thead>
                <tr><th>Tháng</th><th class="num">Tenant mới</th><th class="num">Chuyển Free → Pro</th><th class="num">Tỷ lệ chuyển đổi</th></tr>
            </thead>
            <tbody>
                @foreach ($data['tenantGrowth'] as $month)
                    <tr>
                        <td>{{ $month['label'] }}</td>
                        <td class="num">{{ $month['new_tenants'] }}</td>
                        <td class="num">{{ $month['free_to_pro'] }}</td>
                        <td class="num">{{ $month['conversion_rate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Phân bổ theo gói</h2>
        <table>
            <thead><tr><th>Gói</th><th>Mã</th><th class="num">Số lượng nhà hàng</th></tr></thead>
            <tbody>
                @foreach ($data['planDistribution'] as $plan)
                    <tr><td>{{ $plan['name'] }}</td><td>{{ $plan['code'] }}</td><td class="num">{{ $plan['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <h2>Hiệu suất theo gói (30 ngày qua)</h2>
        <table>
            <thead>
                <tr><th>Gói</th><th class="num">Tenant</th><th class="num">Đơn hàng</th><th class="num">TB đơn/tenant/ngày</th><th class="num">Tỷ lệ tenant hoạt động</th></tr>
            </thead>
            <tbody>
                @foreach ($data['planPerformance'] as $plan)
                    <tr>
                        <td>{{ $plan['plan_name'] }}</td>
                        <td class="num">{{ $plan['tenant_count'] }}</td>
                        <td class="num">{{ $plan['orders_30d'] }}</td>
                        <td class="num">{{ $plan['avg_orders_per_tenant_per_day'] }}</td>
                        <td class="num">{{ $plan['active_tenant_ratio'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Phân tích cohort giữ chân tenant</h2>
        <table>
            <thead>
                <tr><th>Cohort</th><th class="num">Tổng số</th><th class="num">M+1</th><th class="num">M+3</th><th class="num">M+6</th></tr>
            </thead>
            <tbody>
                @foreach ($data['cohortAnalysis'] as $cohort)
                    <tr>
                        <td>{{ $cohort['cohort'] }}</td>
                        <td class="num">{{ $cohort['total'] }}</td>
                        <td class="num">{{ $cohort['m1'] !== null ? $cohort['m1'].'%' : 'N/A' }}</td>
                        <td class="num">{{ $cohort['m3'] !== null ? $cohort['m3'].'%' : 'N/A' }}</td>
                        <td class="num">{{ $cohort['m6'] !== null ? $cohort['m6'].'%' : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Top 5 nhà hàng theo số đơn hàng (30 ngày)</h2>
        <table>
            <thead><tr><th>Nhà hàng</th><th>Mã</th><th class="num">Số đơn hàng</th></tr></thead>
            <tbody>
                @foreach ($data['topOrderRestaurants'] as $restaurant)
                    <tr><td>{{ $restaurant['name'] }}</td><td>{{ $restaurant['code'] ?? '-' }}</td><td class="num">{{ $restaurant['orders_count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <h2>Top 5 nhà hàng theo dung lượng lưu trữ</h2>
        <table>
            <thead><tr><th>Nhà hàng</th><th>Mã</th><th class="num">Dung lượng</th><th class="num">Số tệp</th></tr></thead>
            <tbody>
                @foreach ($data['topStorageRestaurants'] as $restaurant)
                    <tr>
                        <td>{{ $restaurant['name'] }}</td>
                        <td>{{ $restaurant['code'] ?? '-' }}</td>
                        <td class="num">{{ number_format($restaurant['storage_bytes'] / 1048576, 1) }} MB</td>
                        <td class="num">{{ $restaurant['files_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (count($data['alerts']))
            <h2>Cảnh báo đang mở</h2>
            <table>
                <thead><tr><th>Mức độ</th><th>Tiêu đề</th><th>Nội dung</th><th>Thời điểm</th></tr></thead>
                <tbody>
                    @foreach ($data['alerts'] as $alert)
                        <tr>
                            <td><span class="badge {{ $alert['severity'] }}">{{ $alert['severity'] === 'critical' ? 'Nghiêm trọng' : 'Cảnh báo' }}</span></td>
                            <td>{{ $alert['title'] }}</td>
                            <td>{{ $alert['message'] }}</td>
                            <td>{{ $alert['triggered_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
