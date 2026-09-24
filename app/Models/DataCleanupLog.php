<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3.49 — an immutable audit record of one destructive Data
 * Cleanup action. Never updated after creation (no updated_at column —
 * see UPDATED_AT below), and only ever written by DataCleanupLogger,
 * never directly.
 */
class DataCleanupLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'admin_user_id',
        'category',
        'action',
        'criteria',
        'records_affected',
        'files_deleted',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
