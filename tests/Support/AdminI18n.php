<?php

namespace Tests\Support;

/**
 * Finds what is still English-only in the admin panel and what is wrong with
 * its Hindi, by reading the source (no database, no framework boot, so it is
 * fast enough to run in a loop while translating one area).
 *
 *  - unwrapped():   visible text that is not inside __() / t(): text between tags, label/title/placeholder-style
 *                   attributes, string literals inside {{ }}, @section('title', ...), flash / error messages in the
 *                   admin controllers and requests.
 *  - missing():     phrases used through __() / t() that have no Hindi in lang/admin/<area>/hi.json.
 *  - mismatches():  a Hindi text whose :placeholders differ from the English phrase.
 *  - conflicts():   one English phrase translated two different ways in two areas.
 *
 * Strings that must stay English (acronyms, brand names, units) are listed in lang/admin/<area>/allow.php.
 * A file listed under 'files' there is skipped entirely (for example a PDF that is English by design).
 * The command-line report is tests/Support/admin-i18n-report.php.
 */
class AdminI18n
{
    /** Attributes whose static text a person reads. */
    private const TEXT_ATTRIBUTES = 'label|title|placeholder|help|hint|aria-label|alt|empty-text|empty-label|subtitle|description|data-confirm-title|data-confirm-text|data-title|confirm-text|remove-label|tooltip|heading|caption|legend|submit-label|cancel-label|prefix|suffix|helper|text';

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string> absolute paths of the Blade files that make up the admin panel
     */
    public static function bladeFiles(): array
    {
        $views = self::root().'/resources/views';
        $files = [];

        $walk = function (string $dir) use (&$files, &$walk) {
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir.'/'.$entry;
                if (is_dir($path)) {
                    $walk($path);
                } elseif (str_ends_with($entry, '.blade.php')) {
                    $files[] = $path;
                }
            }
        };

        foreach (['admin', 'components/admin', 'components/form', 'components/crud', 'components/ops', 'components/settings'] as $folder) {
            if (is_dir("$views/$folder")) {
                $walk("$views/$folder");
            }
        }

        foreach (['stat-card', 'status-badge', 'status-toggle', 'table-filters', 'sortable-header', 'nav-item', 'selected-report-action'] as $component) {
            if (is_file("$views/components/$component.blade.php")) {
                $files[] = "$views/components/$component.blade.php";
            }
        }

        foreach (array_merge(['layouts/admin.blade.php', 'layouts/guest.blade.php'], array_map(fn ($f) => 'layouts/partials/'.basename($f), glob("$views/layouts/partials/admin-*.blade.php") ?: [])) as $layout) {
            if (is_file("$views/$layout")) {
                $files[] = "$views/$layout";
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @return list<string> PHP files whose user-facing messages are shown in the admin panel
     */
    public static function phpFiles(): array
    {
        $files = [];
        foreach (['app/Http/Controllers/Admin', 'app/Http/Requests/Admin', 'app/Services'] as $folder) {
            $base = self::root().'/'.$folder;
            if (! is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string> the admin JavaScript files that may call t()
     */
    public static function jsFiles(): array
    {
        $files = [];
        foreach (glob(self::root().'/resources/js/*.js') ?: [] as $file) {
            $name = basename($file);
            if (str_starts_with($name, 'admin-') || in_array($name, ['flash.js', 'confirm-delete.js', 'table-selection.js', 'app.js'], true)) {
                $files[] = str_replace('\\', '/', $file);
            }
        }

        return $files;
    }

    /**
     * @return array{strings: list<string>, patterns: list<string>, files: list<string>}
     */
    public static function allowList(): array
    {
        $merged = ['strings' => [], 'patterns' => [], 'files' => []];
        foreach (glob(self::root().'/lang/admin/*/allow.php') ?: [] as $file) {
            $list = require $file;
            foreach (array_keys($merged) as $key) {
                $merged[$key] = array_merge($merged[$key], $list[$key] ?? []);
            }
        }

        return $merged;
    }

    /**
     * English phrase => ['hi' => Hindi, 'file' => the hi.json that holds it].
     *
     * @return array<string, list<array{hi: string, file: string}>>
     */
    public static function translations(): array
    {
        $all = [];
        foreach (glob(self::root().'/lang/admin/*/hi.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data)) {
                throw new \RuntimeException("Invalid JSON in $file");
            }
            foreach ($data as $english => $hindi) {
                $all[$english][] = ['hi' => (string) $hindi, 'file' => basename(dirname($file)).'/hi.json'];
            }
        }

        return $all;
    }

    /**
     * Phrases the admin code asks for through __() / @lang() / trans() / t(), with where they are used.
     *
     * @return array<string, list<string>>
     */
    public static function usedPhrases(?string $only = null): array
    {
        $used = [];
        $add = function (string $phrase, string $file) use (&$used) {
            if (self::isGroupKey($phrase)) {
                return;
            }
            $used[$phrase][] = self::relative($file);
        };

        $literal = '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")';
        $files = array_merge(self::bladeFiles(), self::phpFiles(), self::jsFiles());

        foreach ($files as $file) {
            if ($only !== null && ! str_contains(self::relative($file), $only)) {
                continue;
            }
            $source = (string) file_get_contents($file);
            $pattern = str_ends_with($file, '.js')
                ? "/(?<![\\w.])t\\(\\s*$literal/"
                : "/(?:(?<![\\w])__|@lang|(?<![\\w>])trans)\\(\\s*$literal/";

            if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $add(self::unescape($m[1] !== '' ? $m[1] : ($m[2] ?? '')), $file);
                }
            }
        }

        // Phrases the admin JavaScript asks for are listed per area in lang/admin/<area>/js.php.
        foreach (glob(self::root().'/lang/admin/*/js.php') ?: [] as $file) {
            foreach ((require $file) as $phrase) {
                $add((string) $phrase, $file);
            }
        }

        return $used;
    }

    /**
     * Used phrases with no Hindi text.
     *
     * @return array<string, list<string>>
     */
    public static function missing(?string $only = null): array
    {
        $translations = self::translations();
        $missing = [];
        foreach (self::usedPhrases($only) as $phrase => $files) {
            $hindi = $translations[$phrase][0]['hi'] ?? '';
            if (trim($hindi) === '') {
                $missing[$phrase] = array_values(array_unique($files));
            }
        }

        return $missing;
    }

    /**
     * @return list<string> descriptions of translations whose :placeholders differ from the English phrase
     */
    public static function mismatches(): array
    {
        $problems = [];
        foreach (self::translations() as $english => $entries) {
            foreach ($entries as $entry) {
                $want = self::placeholders($english);
                $have = self::placeholders($entry['hi']);
                if ($want !== $have) {
                    $problems[] = sprintf('%s: "%s" has placeholders [%s] but its Hindi has [%s]', $entry['file'], $english, implode(' ', $want), implode(' ', $have));
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string> an English phrase that two areas translate differently
     */
    public static function conflicts(): array
    {
        $problems = [];
        foreach (self::translations() as $english => $entries) {
            $distinct = array_unique(array_map(fn ($e) => $e['hi'], $entries));
            if (count($distinct) > 1) {
                $problems[] = sprintf('"%s" is translated differently in: %s', $english, implode(', ', array_map(fn ($e) => $e['file'].' => '.$e['hi'], $entries)));
            }
        }

        return $problems;
    }

    /**
     * Visible English that is not translated, per file (relative path => strings).
     *
     * @return array<string, list<string>>
     */
    public static function unwrapped(?string $only = null): array
    {
        $allow = self::allowList();
        $skipFiles = $allow['files'];
        $found = [];

        foreach (self::bladeFiles() as $file) {
            $relative = self::relative($file);
            if (self::skipped($relative, $skipFiles) || ($only !== null && ! str_contains($relative, $only))) {
                continue;
            }
            $strings = self::bladeStrings((string) file_get_contents($file));
            $strings = array_values(array_filter($strings, fn ($s) => ! self::allowed($s, $allow)));
            if ($strings !== []) {
                $found[$relative] = array_values(array_unique($strings));
            }
        }

        foreach (self::phpFiles() as $file) {
            $relative = self::relative($file);
            if (self::skipped($relative, $skipFiles) || ($only !== null && ! str_contains($relative, $only))) {
                continue;
            }
            $strings = self::phpStrings((string) file_get_contents($file));
            $strings = array_values(array_filter($strings, fn ($s) => ! self::allowed($s, $allow)));
            if ($strings !== []) {
                $found[$relative] = array_values(array_unique($strings));
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @return list<string>
     */
    public static function bladeStrings(string $source): array
    {
        $found = [];

        // Comments, scripts and styles are not page text.
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        $source = (string) preg_replace('/<!--.*?-->/s', '', $source);
        $source = (string) preg_replace('/<script\b.*?<\/script>/is', '', $source);
        $source = (string) preg_replace('/<style\b.*?<\/style>/is', '', $source);
        $source = (string) preg_replace('/@php\b(?!\s*\().*?@endphp/s', '', $source);
        $source = (string) preg_replace('/@verbatim.*?@endverbatim/s', '', $source);

        // @section('title', 'Literal') and friends.
        if (preg_match_all('/@section\(\s*\'(?:title|subtitle|heading|description)\'\s*,\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")\s*\)/', $source, $m)) {
            foreach ($m[1] as $quoted) {
                $found[] = self::unescape(substr($quoted, 1, -1));
            }
        }

        // String literals inside {{ }} / {!! !!} that are shown as they are.
        $source = (string) preg_replace_callback('/\{\{(.*?)\}\}|\{!!(.*?)!!\}/s', function ($m) use (&$found) {
            $expression = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
            $expression = (string) preg_replace('/(?:__|trans|trans_choice|t)\(\s*(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/', '', $expression);
            if (preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"/', $expression, $literals)) {
                foreach ($literals[0] as $i => $_) {
                    $text = self::unescape($literals[1][$i] !== '' ? $literals[1][$i] : $literals[2][$i]);
                    if (self::looksLikeEnglishSentence($text)) {
                        $found[] = $text;
                    }
                }
            }

            return "\x01";
        }, $source);

        // Directives (with their arguments) are not text.
        $control = 'if|elseif|foreach|forelse|for|while|isset|unless|can|cannot|canany|switch|case|empty';
        $source = (string) preg_replace('/@(?:'.$control.')\s*(\((?:[^()]++|(?1))*+\))/', '', $source);
        $source = (string) preg_replace('/@[a-zA-Z]+(\((?:[^()]++|(?1))*+\))?/', '', $source);

        // Static attributes a person reads (a bound :label="__('..')" has a colon in front and is skipped).
        if (preg_match_all('/(?<![\w:.@-])(?:'.self::TEXT_ATTRIBUTES.')\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $source, $attributes)) {
            foreach ($attributes[0] as $i => $_) {
                $value = $attributes[1][$i] !== '' ? $attributes[1][$i] : $attributes[2][$i];
                $value = self::cleanText($value);
                if ($value !== '' && preg_match('/[A-Za-z]{2,}/', $value)) {
                    $found[] = $value;
                }
            }
        }

        // Text between tags.
        if (preg_match_all('/>([^<>]+)</', $source, $nodes)) {
            foreach ($nodes[1] as $node) {
                $text = self::cleanText($node);
                if ($text !== '' && preg_match('/[A-Za-z]{2,}/', $text)) {
                    $found[] = $text;
                }
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    public static function phpStrings(string $source): array
    {
        $found = [];
        $literal = '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")';
        $take = function (array $m) use (&$found) {
            foreach ($m[0] as $i => $_) {
                $text = self::unescape(($m[1][$i] ?? '') !== '' ? $m[1][$i] : ($m[2][$i] ?? ''));
                if (self::looksLikeEnglishSentence($text) && ! self::isDeveloperMessage($text)) {
                    $found[] = $text;
                }
            }
        };

        // ->with('success', 'Player added.')   ->with('error', ...)
        if (preg_match_all("/->with\\(\\s*'(?:success|error|warning|info|status)'\\s*,\\s*$literal/", $source, $m)) {
            // preg_match_all groups: 1 = single-quoted, 2 = double-quoted
            $take($m);
        }
        // abort(403, 'Only admins ...')
        if (preg_match_all("/abort\\(\\s*\\d+\\s*,\\s*$literal/", $source, $m)) {
            $take($m);
        }
        // ValidationException::withMessages(['field' => 'Message']) and ->withErrors(['field' => 'Message'])
        if (preg_match_all("/(?:withMessages|withErrors)\\(\\s*\\[[^\\]]*?=>\\s*$literal/s", $source, $m)) {
            $take($m);
        }
        // $validator->errors()->add('field', 'Message')
        if (preg_match_all("/errors\\(\\)->add\\(\\s*'[^']*'\\s*,\\s*$literal/", $source, $m)) {
            $take($m);
        }
        // Custom messages in a FormRequest: 'field.rule' => 'Message'
        if (preg_match('/function messages\(\)[^{]*\{(.*?)\n    \}/s', $source, $body)) {
            if (preg_match_all("/'[a-z_.*]+'\\s*=>\\s*$literal/", $body[1], $m)) {
                $take($m);
            }
        }

        return $found;
    }

    private static function looksLikeEnglishSentence(string $text): bool
    {
        $text = self::cleanText($text);

        return $text !== '' && preg_match('/^[A-Z][^\n]*[a-z]{2,}/', $text) === 1;
    }

    /** Messages for developers, not for a person (class names, SQL, paths). */
    private static function isDeveloperMessage(string $text): bool
    {
        return str_contains($text, '\\') || str_contains($text, '::') || str_contains($text, '->') || preg_match('/^[A-Z][A-Za-z]+(?:[A-Z][a-z]+)+$/', $text) === 1;
    }

    private static function cleanText(string $text): string
    {
        $text = str_replace("\x01", ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private static function unescape(string $text): string
    {
        return str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $text);
    }

    /** A named key such as matches.nav.live belongs to the PHP language files, not to the admin JSON. */
    private static function isGroupKey(string $phrase): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_*-]+)+$/', $phrase) === 1;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $text): array
    {
        preg_match_all('/(?<![\w:]):[A-Za-z_][A-Za-z0-9_]*/', $text, $m);
        $names = array_unique($m[0]);
        sort($names);

        return array_values($names);
    }

    /**
     * @param  array{strings: list<string>, patterns: list<string>, files: list<string>}  $allow
     */
    private static function allowed(string $text, array $allow): bool
    {
        if (in_array($text, $allow['strings'], true)) {
            return true;
        }
        foreach ($allow['patterns'] as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        // Only acronyms and brand names in capitals (RPPL, UTR, PDF ...) are not translated.
        $words = preg_split('/[^A-Za-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            if (! in_array($word, $allow['strings'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $skipFiles
     */
    private static function skipped(string $relative, array $skipFiles): bool
    {
        foreach ($skipFiles as $skip) {
            if ($relative === $skip || str_starts_with($relative, rtrim($skip, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    public static function relative(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $root = str_replace('\\', '/', self::root()).'/';

        return str_replace([$root.'resources/views/', $root], '', $file);
    }
}
