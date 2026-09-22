<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One persisted (group, key) => value row for the Settings module (Phase
 * 3.44B1). Deliberately thin — this model has no opinion on what a value
 * MEANS: no cast on `value` (it stores every supported type as a plain
 * string/null), no automatic encryption, no global scope, no observer.
 * SettingsRegistry (the allow-list of valid keys/types/defaults) and
 * SettingsService (reads/writes/caching/encryption) own all of that
 * intentionally, so this model can never be the place a type/encryption
 * bug hides behind "magic" Eloquent behavior.
 *
 * A row existing at all is itself meaningful: SettingsService treats "no
 * row" and "row with value IS NULL" identically (both mean "no persisted
 * override — use the registry default"), so this model never needs to
 * distinguish the two either.
 */
class Setting extends Model
{
    use HasFactory;

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_DECIMAL = 'decimal';

    public const TYPE_COLOR = 'color';

    public const TYPE_IMAGE = 'image';

    public const TYPE_ENCRYPTED = 'encrypted';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_STRING,
        self::TYPE_TEXT,
        self::TYPE_BOOLEAN,
        self::TYPE_INTEGER,
        self::TYPE_DECIMAL,
        self::TYPE_COLOR,
        self::TYPE_IMAGE,
        self::TYPE_ENCRYPTED,
    ];

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];
}
