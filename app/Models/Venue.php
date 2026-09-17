<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'country',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    /**
     * Local scope, not a global scope — historical matches must still
     * be able to resolve an inactive venue normally.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
