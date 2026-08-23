<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankStatementLine extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date' => 'date',
            'amount_in' => 'decimal:2',
            'amount_out' => 'decimal:2',
            'balance' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'raw_payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialBankAccount::class, 'financial_bank_account_id');
    }

    public function matched(): MorphTo
    {
        return $this->morphTo('matched', 'matched_type', 'matched_id');
    }
}
