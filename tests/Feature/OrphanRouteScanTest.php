<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Chốt chặn kiến trúc: route của tenant phải có nơi gọi ở frontend.
 *
 * Trang "Hiệu quả khuyến mãi" và "Trigger tự động" từng có controller, service
 * và trang Vue hoàn chỉnh nhưng không một link nào trỏ tới — người dùng chỉ vào
 * được bằng cách gõ tay URL. Đó là công sức đã bỏ ra mà không ai dùng được.
 *
 * Test này không đòi mọi route phải có UI: nó đòi mỗi route KHÔNG có UI phải
 * được khai báo là cố ý, kèm lý do. Con số dưới đây là hiện trạng đã kiểm kê;
 * mỗi lần dọn bớt thì xoá dòng tương ứng đi.
 */
class OrphanRouteScanTest extends TestCase
{
    // gatherMiddleware() dựng middleware của controller, trong đó có nhánh tra
    // cứu bảng permissions — nên test này cần schema thật.
    use RefreshDatabase;

    /**
     * Route chưa có UI, đã kiểm kê ngày 25/08/2026.
     *
     * Mỗi dòng là một khoản nợ kỹ thuật cần quyết định: nối UI hoặc xoá route.
     * KHÔNG thêm dòng mới vào đây khi viết tính năng mới — hãy nối UI luôn.
     *
     * @var array<string, string>
     */
    private array $knownOrphans = [
        // Cập nhật 25/08/2026: 18 dòng đã bị xoá khỏi đây sau khi test học được
        // cách đọc helper Wayfinder — chúng vẫn luôn được gọi, chỉ là gọi bằng
        // hàm sinh tự động nên máy quét cũ không nhìn thấy.

        // ── Kho, chuỗi cung ứng & điều phối nhân sự kho ────────────────────
        'api.warehouse.team.kpi' => 'KPI nhân sự kho: chưa có giao diện.',
        'warehouse.tasks.quick-auto-assign' => 'Tự động giao việc kho: chưa có nút.',
        'warehouse.my-shift-handover' => 'Bàn giao ca kho: chưa có giao diện.',
        'warehouse.supply-chain.alerts' => 'Cảnh báo chuỗi cung ứng: chưa có giao diện.',
        'warehouse.supply-chain.reconciliation' => 'Đối soát chuỗi cung ứng: chưa có giao diện.',
        'warehouse.ingredients.suppliers' => 'Nhà cung cấp theo nguyên liệu: chưa có giao diện.',
        'warehouse.ingredients.suppliers.sync' => 'Đồng bộ nhà cung cấp theo nguyên liệu: chưa có giao diện.',
        'warehouse-fraud-cases.index' => 'Hồ sơ gian lận kho: chưa có giao diện.',
        'warehouse-fraud-cases.assign' => 'Hồ sơ gian lận kho: chưa có giao diện.',
        'warehouse-fraud-cases.update-status' => 'Hồ sơ gian lận kho: chưa có giao diện.',
        'supply-requests.task-board' => 'Bảng công việc yêu cầu cung ứng: chưa có giao diện.',
        'supply-requests.set-central-branch' => 'Đặt kho trung tâm: chưa có giao diện.',
        'supply-requests.quick-recommended' => 'Tạo nhanh yêu cầu cung ứng: chưa có nút.',

        // ── Nhà cung cấp ───────────────────────────────────────────────────
        'purchase-orders.send-delivery-reminder' => 'Nhắc giao hàng: chưa có nút.',

        // ── Phân tích đã viết xong nhưng chưa có màn hình ──────────────────
        'waste.dashboard' => 'Module Quản lý lãng phí chưa có giao diện.',
        'waste.trend' => 'Module Quản lý lãng phí chưa có giao diện.',
        'waste.suggestions' => 'Module Quản lý lãng phí chưa có giao diện.',
        'waste.expiring' => 'Module Quản lý lãng phí chưa có giao diện.',
        'menu-engineering.scoring' => 'Chấm điểm thực đơn: trang chưa gọi API này.',
        'menu-engineering.display-order' => 'Kéo thả sắp xếp thực đơn: chưa có giao diện.',
        'geo-analytics.heatmap' => 'Bản đồ nhiệt: trang chưa gọi API này.',
        'cash-flow.forecast' => 'Dự báo dòng tiền: chưa có giao diện.',
        'customers.cdp.segment' => 'Chi tiết phân khúc khách hàng: chưa có giao diện.',
        'checklist.weekly-report' => 'Báo cáo checklist tuần: chưa có giao diện.',
        'operation-policies.check' => 'API kiểm tra chính sách vận hành: gọi từ backend, chưa dùng ở frontend.',

        // ── Báo cáo & đối soát ─────────────────────────────────────────────
        // export-pdf đã được nối nút ngày 25/08/2026.
        // Hai route CSV/Excel dưới đây trả về 7 cột, trong khi nút "Xuất CSV"
        // sẵn có trên trang tự dựng file 14 cột từ props. Không nối UI để tránh
        // hai bản báo cáo "chính thức" cho ra số khác nhau; giữ endpoint cho
        // tích hợp ngoài (cron, script đối soát).
        'reports.export-csv' => 'Trùng chức năng với nút Xuất CSV trên trang; giữ cho tích hợp ngoài.',
        'reports.export-excel' => 'Alias của export-csv; giữ cho tích hợp ngoài.',
        // 4 route đối soát ngân hàng đã được nối giao diện ngày 25/08/2026.
        'cash-handovers.store' => 'Bàn giao ca tiền mặt: chưa có giao diện.',
        'cash-handovers.acknowledge' => 'Bàn giao ca tiền mặt: chưa có giao diện.',
        'cash-handovers.dispute' => 'Bàn giao ca tiền mặt: chưa có giao diện.',

        // ── Khách hàng thân thiết ──────────────────────────────────────────
        'loyalty.adjust-points' => 'Điều chỉnh điểm thủ công: chưa có giao diện.',
        'loyalty.transactions' => 'Lịch sử giao dịch điểm: chưa có giao diện.',
        'loyalty.qr' => 'QR thẻ thành viên: chưa có giao diện.',

        // ── Trợ lý AI chiến lược ───────────────────────────────────────────

        // ── Thao tác hàng loạt ─────────────────────────────────────────────
        'approvals.batch-approve-low-risk' => 'Duyệt hàng loạt yêu cầu rủi ro thấp: chưa có nút.',
        'attendance.batch-approve-normal' => 'Duyệt hàng loạt chấm công: chưa có nút.',
        'delivery.batches.mark-all-picked-up' => 'Đánh dấu đã lấy toàn bộ lô giao: chưa có nút.',
        'products.toggle-out-of-stock-ingredients' => 'Bật/tắt món theo nguyên liệu hết: chưa có nút.',

        // ── Đã có UI nhưng gọi qua helper Wayfinder nên máy quét không thấy ─
        'billing.pay' => 'Trang thanh toán mở từ email/redirect, không có link tĩnh trong mã frontend.',
        'user-password.update' => 'Trang Đổi mật khẩu gọi qua helper sinh tự động trong resources/js/routes.',
    ];

    public function test_tenant_routes_are_reachable_from_the_frontend(): void
    {
        $frontend = $this->frontendSource();
        $unlisted = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $name = $route->getName() ?? '';
            $uri = $route->uri();

            if (! str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue;
            }
            if (str_contains($action, '\\SuperAdmin\\') || str_contains($action, '\\Auth\\')) {
                continue;
            }
            if (! $this->isTenantRoute($route)) {
                continue;
            }

            // Đường dẫn tĩnh trước tham số đầu tiên là thứ frontend thật sự viết ra.
            $stem = '/'.trim(explode('{', $uri)[0], '/');
            if ($stem === '/') {
                continue;
            }

            if (str_contains($frontend, $stem)) {
                continue;
            }
            if ($name !== '' && str_contains($frontend, $name)) {
                continue;
            }
            // Wayfinder sinh sẵn một hàm helper cho mọi route; trang gọi
            // ocrInvoice.url() chứ không viết chuỗi '/suppliers/ocr-invoice'.
            // Bỏ qua bước này thì 18 route đang chạy tốt bị báo nhầm là mồ côi.
            if ($this->isCalledThroughWayfinderHelper($uri)) {
                continue;
            }
            if ($name !== '' && isset($this->knownOrphans[$name])) {
                continue;
            }

            $unlisted[$name ?: $uri] = sprintf('%s  (%s /%s)', $name ?: '(không tên)', implode('|', array_diff($route->methods(), ['HEAD'])), $uri);
        }

        $this->assertSame([], array_values($unlisted), sprintf(
            "Có %d route không có nơi nào ở frontend gọi tới và cũng chưa được khai báo.\n\n%s\n\n".
            "Cách xử lý:\n".
            "  1. Nối UI cho route (ưu tiên — tính năng đã viết thì nên dùng được), HOẶC\n".
            "  2. Xoá route nếu tính năng đã bỏ, HOẶC\n".
            "  3. Nếu là API nội bộ/đang làm dở, khai báo vào \$knownOrphans KÈM LÝ DO.\n",
            count($unlisted),
            implode("\n", array_map(fn ($v) => '  - '.$v, $unlisted)),
        ));
    }

    public function test_the_orphan_list_has_no_stale_entries(): void
    {
        // Khi một route được nối UI hoặc bị xoá, dòng khai báo phải được dọn đi,
        // nếu không danh sách nợ kỹ thuật sẽ phình mãi mà không ai rà lại.
        $frontend = $this->frontendSource();
        $names = [];
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if ($route->getName()) {
                $names[$route->getName()] = '/'.trim(explode('{', $route->uri())[0], '/');
                $uris[$route->getName()] = $route->uri();
            }
        }

        $stale = [];
        foreach ($this->knownOrphans as $name => $reason) {
            if (! isset($names[$name])) {
                $stale[] = "{$name} — route không còn tồn tại, xoá khỏi danh sách.";

                continue;
            }
            if (
                str_contains($frontend, $names[$name])
                || str_contains($frontend, $name)
                || $this->isCalledThroughWayfinderHelper($uris[$name])
            ) {
                $stale[] = "{$name} — đã có UI gọi tới, xoá khỏi danh sách.";
            }
        }

        $this->assertSame([], $stale, "Danh sách route mồ côi đã lỗi thời:\n".implode("\n", $stale));
    }

    private function isTenantRoute(\Illuminate\Routing\Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && (str_contains($m, 'Authenticate') || $m === 'auth' || str_starts_with($m, 'auth:'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trang có gọi route này qua helper do Wayfinder sinh không?
     *
     * Ghép theo chú thích @route trong tệp sinh tự động — chính xác hơn nhiều so
     * với việc đoán tên hàm từ tên route.
     */
    private function isCalledThroughWayfinderHelper(string $uri): bool
    {
        foreach ($this->wayfinderHelpers()[trim($uri, '/')] ?? [] as [$module, $export]) {
            foreach ($this->frontendImports() as [$importedModule, $importedName]) {
                if ($importedModule === $module && ($importedName === $export || $importedName === '*default*')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * uri => list<array{0: string, 1: string}> (module, tên export)
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function wayfinderHelpers(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $base = base_path('resources/js/routes');
        if (! is_dir($base)) {
            return $map;
        }

        $finder = (new Finder)->files()->in($base)->name('*.ts');

        foreach ($finder as $file) {
            $src = $file->getContents();
            $module = str_replace('\\', '/', $file->getRelativePathname());
            $module = preg_replace('#/index\.ts$|\.ts$#', '', $module);
            $module = '@/routes'.($module !== '' ? '/'.$module : '');

            preg_match_all('/export const (\w+)\s*=/', $src, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match) {
                [$export, $offset] = $match;
                $before = substr($src, max(0, $offset - 500), min(500, $offset));

                if (preg_match_all("/@route '([^']+)'/", $before, $routeMatches)) {
                    $uri = trim(end($routeMatches[1]), '/');
                    $map[$uri][] = [$module, $export];
                }
            }
        }

        return $map;
    }

    /**
     * Những gì mã trang thật sự import từ @/routes.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function frontendImports(): array
    {
        static $imports = null;
        if ($imports !== null) {
            return $imports;
        }

        $imports = [];
        $src = $this->frontendSource();

        if (preg_match_all("/import\s*\{([^}]*)\}\s*from\s*'(@\/routes[^']*)'/s", $src, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                foreach (explode(',', $match[1]) as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }
                    $imports[] = [$match[2], trim(explode(' as ', $part)[0])];
                }
            }
        }

        if (preg_match_all("/import\s+\w+\s+from\s*'(@\/routes[^']*)'/", $src, $matches)) {
            foreach ($matches[1] as $module) {
                $imports[] = [$module, '*default*'];
            }
        }

        return $imports;
    }

    private function frontendSource(): string
    {
        $finder = new Finder;
        $finder->files()
            ->in(base_path('resources/js'))
            // Hai thư mục này do Wayfinder sinh tự động cho MỌI route, nên nếu
            // tính vào thì không route nào bị coi là mồ côi.
            ->exclude('routes')
            ->exclude('actions')
            ->name(['*.vue', '*.ts', '*.js']);

        $buffer = '';
        foreach ($finder as $file) {
            $buffer .= $file->getContents()."\n";
        }

        return $buffer;
    }
}
