<?php

namespace App\Notifications;

use App\Models\RequestForProposal;
use App\Models\RfpBid;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupplierRfpDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private RequestForProposal $rfp,
        private RfpBid $bid,
        private bool $accepted,
    ) {}

    public function via(object $notifiable): array
    {
        return (bool) config('portal.supplier_portal_enabled', false)
            ? ['database']
            : [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->accepted ? 'supplier_rfp_accepted' : 'supplier_rfp_rejected',
            'title' => $this->accepted ? 'Hồ sơ chào giá đã được chọn' : 'Hồ sơ chào giá chưa được chọn',
            'message' => $this->accepted
                ? 'Hồ sơ của bạn cho RFP "'.$this->rfp->title.'" đã được chấp nhận.'
                : 'Hồ sơ của bạn cho RFP "'.$this->rfp->title.'" đã được đóng mà không được chọn.',
            'rfp_id' => $this->rfp->id,
            'bid_id' => $this->bid->id,
            'url' => '/supplier/rfps',
        ];
    }
}
