<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3.48 — a Contributor's committee membership for one specific
 * Edition. Being on the committee is edition-specific, not a separate
 * person identity: the person identity is always Contributor. A unique
 * (edition_id, contributor_id) constraint (see the creating migration)
 * prevents the same person being added twice to the same edition's
 * committee.
 */
class EditionCommitteeMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'edition_id',
        'contributor_id',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }
}
