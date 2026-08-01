<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfpItem extends Model
{
    use HasFactory;

    protected $table = 'rfp_items';

    protected $guarded = [];

    public function rfp(): BelongsTo
    {
        return $this->belongsTo(RequestForProposal::class, 'rfp_id');
    }
}
