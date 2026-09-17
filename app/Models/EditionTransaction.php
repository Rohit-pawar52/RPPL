<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single manual income/expense entry for an edition's operational
 * ledger. Deliberately independent of PlayerRegistration.payment_status/
 * registration_fee — this is a separate manual bookkeeping concept, not
 * a payment-processing record, and the two are not synchronized.
 *
 * A row created automatically by EditionContributionService (Phase
 * 3.26) carries a contribution() — such rows must never be edited or
 * deleted through the generic Finance CRUD (see
 * EditionTransactionController), only through the contribution itself.
 */
class EditionTransaction extends Model
{
    use HasFactory;

    /**
     * Must match the enum values in the edition_transactions migration
     * exactly.
     */
    public const TYPES = ['income', 'expense'];

    protected $fillable = [
        'edition_id',
        'type',
        'category',
        'amount',
        'transaction_date',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contribution(): HasOne
    {
        return $this->hasOne(EditionContribution::class, 'edition_transaction_id');
    }
}
