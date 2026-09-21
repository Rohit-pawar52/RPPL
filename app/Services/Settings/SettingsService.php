<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/**
 * The canonical application access point for settings (Phase 3.44B1).
 * Every read/write goes through here — never a direct Setting::query()
 * call from a controller/view, so caching and the encrypted-value
 * boundary can never accidentally be bypassed.
 *
 * The whole (tiny) table is cached as one entry (see $this->persisted())
 * — this table will only ever hold a few dozen rows, so per-key cache
 * entries would be pure overhead. Every write calls flush() before
 * returning, so the very next read (in the same request or a later one)
 * always sees the new value — never a stale cache window.
 */
class SettingsService
{
    private const CACHE_KEY = 'settings:all';

    /**
     * A sentinel distinguishing "no $fallback argument was passed" from
     * "the caller explicitly passed null" — get()'s fallback semantics
     * (see its own docblock) need that distinction, and a plain `null`
     * default parameter cannot express it. The embedded NUL bytes make
     * this value impossible for any real caller to pass by accident.
     */
    private const NO_FALLBACK = "\0__settings_no_fallback__\0";

    /**
     * Reads a setting's current value.
     *
     * Resolution order:
     * 1. A persisted, non-null value for this key.
     * 2. The caller's own $fallback, if one was explicitly passed
     *    (including `null` itself) — an explicit fallback ALWAYS wins
     *    over the registry default, since the caller is asking for a
     *    specific value for ITS context, not RPPL's global default.
     * 3. SettingsRegistry's own default for this key.
     *
     * An unknown (unregistered) key, or a key of type `encrypted`,
     * NEVER returns a persisted/decrypted value here — only whatever
     * $fallback resolves to (null if none given). Encrypted values are
     * only ever readable via getEncrypted(), so generic presentation
     * code (a Blade view, a report) can never accidentally leak a
     * secret just by calling the normal accessor.
     */
    public function get(string $key, mixed $fallback = self::NO_FALLBACK): mixed
    {
        $hasExplicitFallback = $fallback !== self::NO_FALLBACK;

        if (! SettingsRegistry::has($key) || SettingsRegistry::type($key) === Setting::TYPE_ENCRYPTED) {
            return $hasExplicitFallback ? $fallback : null;
        }

        $stored = $this->storedValue($key);

        if ($stored !== null) {
            return $stored;
        }

        return $hasExplicitFallback ? $fallback : SettingsRegistry::default($key);
    }

    public function boolean(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function integer(string $key): ?int
    {
        $value = $this->get($key);

        return $value === null ? null : (int) $value;
    }

    /**
     * The only path that ever decrypts a secret — intended for
     * server-side use only (a future payment-integration service), never
     * a Blade view. Throws for anything that isn't actually a registered
     * `encrypted` setting, since calling this on the wrong key is a
     * programmer error, not a runtime data condition. Corrupted/
     * undecryptable ciphertext (e.g. APP_KEY rotated without
     * re-encrypting) fails safely to null — the exception message itself
     * is never exposed, and the ciphertext is never included in any
     * error output.
     */
    public function getEncrypted(string $key): ?string
    {
        if (! SettingsRegistry::has($key) || SettingsRegistry::type($key) !== Setting::TYPE_ENCRYPTED) {
            throw new InvalidArgumentException("[{$key}] is not a registered encrypted setting.");
        }

        $stored = $this->rawPersistedValue($key);

        if ($stored === null) {
            return SettingsRegistry::default($key);
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Writes exactly one registered setting. Rejects any key
     * SettingsRegistry doesn't recognize — settings are allow-listed,
     * never freely creatable, so a request field that doesn't map to a
     * real setting can never silently create a new row.
     */
    public function set(string $key, mixed $value): void
    {
        if (! SettingsRegistry::has($key)) {
            throw new InvalidArgumentException("[{$key}] is not a registered setting.");
        }

        $type = SettingsRegistry::type($key);
        [$group, $settingKey] = $this->splitKey($key);

        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            ['value' => $this->normalize($type, $value), 'type' => $type],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The registered type's own natural PHP representation of this
     * key's persisted value (bool for `boolean`, int for `integer`,
     * float for `decimal`, the raw string otherwise) — or null if no
     * row exists, or the row's value column is itself null. Never
     * called for an `encrypted` key (get() already short-circuits
     * before reaching here).
     */
    private function storedValue(string $key): mixed
    {
        $raw = $this->rawPersistedValue($key);

        if ($raw === null) {
            return null;
        }

        return match (SettingsRegistry::type($key)) {
            Setting::TYPE_BOOLEAN => $raw === '1',
            Setting::TYPE_INTEGER => (int) $raw,
            Setting::TYPE_DECIMAL => (float) $raw,
            default => $raw,
        };
    }

    /**
     * The exact string stored in settings.value for this key, with no
     * type interpretation at all — used both by storedValue() (for
     * every non-encrypted type) and getEncrypted() (which decrypts it
     * itself). Null when no row exists OR the row's value is null —
     * SettingsService treats both cases identically throughout.
     */
    private function rawPersistedValue(string $key): ?string
    {
        $all = $this->persisted();

        return $all[$key] ?? null;
    }

    /**
     * @return array<string, string|null>
     */
    private function persisted(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function () {
            return Setting::query()
                ->get(['group', 'key', 'value'])
                ->mapWithKeys(fn (Setting $setting) => ["{$setting->group}.{$setting->key}" => $setting->value])
                ->all();
        });
    }

    /**
     * Normalizes an incoming value to the plain string (or null) that
     * settings.value actually stores — deliberately simple, no
     * serialize()/JSON: booleans as '1'/'0', integers/decimals as their
     * string form, encrypted values via Laravel's own Crypt, everything
     * else as a plain nullable string. Color/image values are stored
     * as-is; validating their shape belongs to the future admin
     * FormRequest (Phase 3.44B2), not this service.
     */
    private function normalize(string $type, mixed $value): ?string
    {
        return match ($type) {
            Setting::TYPE_BOOLEAN => $value ? '1' : '0',
            Setting::TYPE_INTEGER => $value === null ? null : (string) (int) $value,
            Setting::TYPE_DECIMAL => $value === null ? null : (string) (float) $value,
            Setting::TYPE_ENCRYPTED => $value === null ? null : Crypt::encryptString((string) $value),
            default => $value === null ? null : (string) $value,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitKey(string $key): array
    {
        return explode('.', $key, 2);
    }
}
