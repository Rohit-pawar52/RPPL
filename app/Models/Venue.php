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
        'village',
        'tehsil',
        'district',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // decimal columns come back as strings by default; the map code
            // and the directions link want real numbers.
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * "Village, Tehsil, District" for a village ground; falls back to the
     * older "City, Country" text for rows created before village existed.
     */
    public function locationLabel(): string
    {
        $parts = collect([$this->village, $this->tehsil, $this->district])->filter();

        if ($parts->isEmpty()) {
            $parts = collect([$this->city, $this->country])->filter();
        }

        return $parts->implode(', ');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Google Maps directions link to the exact pin, or null without one.
     */
    public function directionsUrl(): ?string
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        $format = fn (float $value) => rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');

        return 'https://www.google.com/maps/dir/?api=1&destination='.$format($this->latitude).','.$format($this->longitude);
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
