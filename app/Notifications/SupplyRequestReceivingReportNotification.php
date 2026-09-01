<?php

namespace App\Notifications;

use App\Models\SupplyRequestReceivingReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupplyRequestReceivingReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        private SupplyRequestReceivingReport $report,
        private string $stage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $request = $this->report->supplyRequest;
        $requestCode = $request?->request_code ?? 'đơn cấp phát';
        $branchName = $request?->toBranch?->name ?? 'chi nhánh';
        $isDriver = (int) ($request?->transporter_id ?? 0) === (int) ($notifiable->id ?? 0);
        $isCentralUser = $notifiable instanceof User && ($notifiable->isSuperAdmin() || $notifiable->isOwner() || $notifiable->hasRole('warehouse_manager'));

        [$title, $message] = match ($this->stage) {
            'confirmed' => $isDriver
                ? [
                    'Biên bản nhận hàng cần tài xế xác nhận',
                    "Biên bản {$this->report->report_code} của đơn {$requestCode} tại {$branchName} cần bạn xác nhận.",
                ]
                : [
                    'Biên bản nhận hàng chờ xử lý',
                    "Biên bản {$this->report->report_code} của đơn {$requestCode} đã được chi nhánh xác nhận; hàng lỗi đang được cách ly.",
                ],
            'driver_confirmed' => [
                'Tài xế đã xác nhận biên bản nhận hàng',
                "Tài xế đã xác nhận biên bản {$this->report->report_code}; vui lòng xử lý phần chênh lệch/cách ly.",
            ],
            'resolved' => [
                'Biên bản nhận hàng đã được xử lý',
                "Biên bản {$this->report->report_code} của đơn {$requestCode} đã được ghi nhận kết luận.",
            ],
            default => [
                'Cập nhật biên bản nhận hàng',
                "Biên bản {$this->report->report_code} có cập nhật mới.",
            ],
        };

        $url = $isDriver
            ? '/inventory/staff-portal?tab=incident&report_id='.$this->report->id
            : ($isCentralUser
                ? '/inventory/warehouse-governance?report_id='.$this->report->id
                : '/inventory/branch-requisition?branch_id='.($request?->to_branch_id ?? ($notifiable instanceof User ? $notifiable->assignedBranchId() : 0)).'&report_id='.$this->report->id);

        return [
            'type' => 'supply_request_receiving_report_'.$this->stage,
            'stage' => $this->stage,
            'title' => $title,
            'message' => $message,
            'report_id' => $this->report->id,
            'report_code' => $this->report->report_code,
            'supply_request_id' => $request?->id,
            'request_code' => $requestCode,
            'branch_id' => $request?->to_branch_id,
            'url' => $url,
        ];
    }
}
