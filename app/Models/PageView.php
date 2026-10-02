<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One counted public detail-page visit. Append-only: written by
 * PageViewRecorder, never updated. local_date is deliberately NOT cast —
 * it is a plain Y-m-d string so SQLite (tests) and MySQL store and compare
 * the same value.
 */
class PageView extends Model
{
    public const MATCH_VIEW = 'match_view';

    public const EDITION_VIEW = 'edition_view';

    public const PLAYER_VIEW = 'player_view';

    public const EVENT_TYPES = [self::MATCH_VIEW, self::EDITION_VIEW, self::PLAYER_VIEW];

    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'subject_id',
        'visitor_hash',
        'viewed_at',
        'local_date',
        'local_hour',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'viewed_at' => 'datetime',
            'local_hour' => 'integer',
        ];
    }
}
