<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'is_active',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'photo_path',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Whether this user's role allows the permission (see App\Support\Permissions). Deny by default:
     * a user without a role, or a key that is not in the catalog, is never allowed.
     */
    public function hasPermission(string $key): bool
    {
        return $this->role?->hasPermission($key) ?? false;
    }

    /**
     * @param  list<string>  $keys
     */
    public function hasAnyPermission(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->hasPermission($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * players.user_id is UNIQUE, so a user has at most one player profile.
     */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    /**
     * V1 never sets fcm_tokens.user_id (every subscription is anonymous
     * — see FcmTokenSubscriptionService) — this relation exists now
     * purely so a future identified-user association needs no schema
     * change, per the Phase B1 audit report.
     */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }
}
