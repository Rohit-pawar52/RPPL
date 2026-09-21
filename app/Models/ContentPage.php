<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One of a small, FIXED set of system-defined public pages (Phase
 * 3.46) — Privacy Policy, Terms & Conditions, FAQs. `type` is the
 * canonical, never-admin-editable identity of a row (enforced at the
 * database level by a unique constraint, and never accepted from
 * request input — see UpdateContentPageRequest/ContentPageController).
 * There is no create/delete UI: the three canonical rows always exist
 * via deterministic seeding, and admins only ever edit title/content/
 * is_active on an existing row.
 */
class ContentPage extends Model
{
    use HasFactory;

    public const TYPE_PRIVACY_POLICY = 'privacy_policy';

    public const TYPE_TERMS_CONDITIONS = 'terms_conditions';

    public const TYPE_FAQS = 'faqs';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_PRIVACY_POLICY,
        self::TYPE_TERMS_CONDITIONS,
        self::TYPE_FAQS,
    ];

    /**
     * The default title for a canonical type — used only by
     * DemoContentPageSeeder to create a row the first time; once a row
     * exists, its own `title` column (admin-editable) always wins.
     *
     * @var array<string, string>
     */
    public const DEFAULT_TITLES = [
        self::TYPE_PRIVACY_POLICY => 'Privacy Policy',
        self::TYPE_TERMS_CONDITIONS => 'Terms & Conditions',
        self::TYPE_FAQS => 'Frequently Asked Questions',
    ];

    /**
     * The one named public route for each canonical type — the single
     * place mapping `type` to a URL, shared by the footer links and
     * (indirectly, via routes/web.php's explicit route definitions) the
     * public pages themselves.
     *
     * @var array<string, string>
     */
    public const PUBLIC_ROUTE_NAMES = [
        self::TYPE_PRIVACY_POLICY => 'public.privacy-policy',
        self::TYPE_TERMS_CONDITIONS => 'public.terms-conditions',
        self::TYPE_FAQS => 'public.faqs',
    ];

    protected $fillable = [
        'type',
        'title',
        'content',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function publicUrl(): string
    {
        return route(self::PUBLIC_ROUTE_NAMES[$this->type]);
    }
}
