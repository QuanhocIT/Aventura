<?php

namespace App\Notifications;

use App\Models\WarehouseReceivingReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarehouseReceivingReportNotification extends Notification
{
    use Queueable;

    public function __construct(private WarehouseReceivingReport $report) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $voucher = $this->report->voucher;
        $source = $voucher?->external_source_name ?: 'nguồn bên ngoài';
        $checker = $this->report->employeeConfirmedBy?->name ?: 'nhân viên Kho Tổng';
        $difference = (float) $this->report->total_discrepancy_qty;
        $issue = $this->report->issue_type === 'quality_issue'
            ? 'vấn đề chất lượng'
            : ($this->report->issue_type === 'quantity_and_quality'
                ? 'chênh lệch số lượng và vấn đề chất lượng'
                : 'chênh lệch số lượng');

        return [
            'type' => 'warehouse_receiving_report_created',
            'title' => 'Biên bản kiểm kê phiếu nhập ngoài',
            'message' => "Biên bản {$this->report->report_code} của phiếu {$voucher?->voucher_code} từ {$source} đã được {$checker} xác nhận, ghi nhận {$issue} (chênh lệch {$difference}). Vui lòng kiểm tra.",
            'report_id' => $this->report->id,
            'report_code' => $this->report->report_code,
            'voucher_id' => $voucher?->id,
            'voucher_code' => $voucher?->voucher_code,
            'issue_type' => $this->report->issue_type,
            'total_expected_qty' => (float) $this->report->total_expected_qty,
            'total_actual_qty' => (float) $this->report->total_actual_qty,
            'discrepancy_qty' => $difference,
            'quality_status' => $this->report->quality_status,
            'confirmed_by' => $checker,
            'url' => '/inventory/staff-portal?tab=incident&report_id='.$this->report->id,
        ];
    }
}
