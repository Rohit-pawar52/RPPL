<?php

namespace Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;
use Tests\Support\AdminI18n;

/**
 * Keeps the admin panel fully bilingual: every phrase an admin screen shows goes through __() / t() and has
 * Hindi in lang/admin/<area>/hi.json. A new screen with hard-coded English fails here, so Hindi cannot quietly
 * fall behind. (Plain PHPUnit: it only reads source files, so it needs no database and is fast.)
 * To see the list while working: php tests/Support/admin-i18n-report.php [path-part].
 */
class AdminTranslationCoverageTest extends TestCase
{
    private function lines(array $items, int $limit = 40): string
    {
        $lines = [];
        foreach ($items as $key => $value) {
            $lines[] = is_int($key) ? (string) $value : $key.' => '.(is_array($value) ? implode(' | ', array_slice($value, 0, 4)) : $value);
        }

        return "\n  ".implode("\n  ", array_slice($lines, 0, $limit)).(count($lines) > $limit ? "\n  ... and ".(count($lines) - $limit).' more' : '');
    }

    public function test_no_visible_english_is_left_untranslated_in_the_admin_panel(): void
    {
        $unwrapped = AdminI18n::unwrapped();

        $this->assertSame([], $unwrapped, 'Wrap this text in __() (or t() in JavaScript) and add its Hindi to lang/admin/<area>/hi.json; if it must stay English, list it in that area\'s allow.php:'.$this->lines($unwrapped));
    }

    public function test_every_phrase_used_through_the_translator_has_hindi(): void
    {
        $missing = AdminI18n::missing();

        $this->assertSame([], $missing, 'These phrases have no Hindi in lang/admin/<area>/hi.json:'.$this->lines($missing));
    }

    public function test_hindi_texts_keep_the_same_placeholders_as_the_english_phrase(): void
    {
        $this->assertSame([], AdminI18n::mismatches(), 'A Hindi text must keep every :placeholder of its English phrase:'.$this->lines(AdminI18n::mismatches()));
    }

    public function test_one_english_phrase_is_translated_one_way_everywhere(): void
    {
        $this->assertSame([], AdminI18n::conflicts(), 'Pick one Hindi text for each of these and use it in every area:'.$this->lines(AdminI18n::conflicts()));
    }

    public function test_a_phrase_named_like_a_language_file_has_an_english_entry_too(): void
    {
        // __('Matches') looks the phrase up in the JSON files first, and only then as a file (lang/en/matches.php).
        // On Windows file names are case-insensitive, so a phrase like "Matches" with no JSON entry would return that
        // file's whole array and crash the page. lang/admin/common/en.json holds identity entries for such words.
        $groups = array_map(fn ($f) => strtolower(basename($f, '.php')), glob(dirname(__DIR__, 3).'/lang/en/*.php'));
        $english = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/lang/admin/common/en.json'), true);
        $unsafe = [];

        foreach (array_keys(AdminI18n::usedPhrases()) as $phrase) {
            if (in_array(strtolower($phrase), $groups, true) && ! isset($english[$phrase])) {
                $unsafe[] = $phrase;
            }
        }

        $this->assertSame([], $unsafe, 'Add these to lang/admin/common/en.json as identity entries ("Matches": "Matches"):'.$this->lines($unsafe));
    }

    public function test_every_translation_file_is_valid_and_has_text_for_each_phrase(): void
    {
        $empty = [];
        foreach (AdminI18n::translations() as $english => $entries) {
            foreach ($entries as $entry) {
                if (trim($entry['hi']) === '') {
                    $empty[] = $entry['file'].': "'.$english.'" has an empty Hindi text';
                }
            }
        }

        $this->assertSame([], $empty);
    }
}
