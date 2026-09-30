<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-uploaded RPPL photo shown in the public gallery (/photos).
 * photo_path is a storage path on the 'public' disk (see PhotoService).
 * Mirrors Video: string status, priority ASC then newest first.
 */
class Photo extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'title',
        'description',
        'photo_path',
        'status',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderByDesc('id');
    }
}
