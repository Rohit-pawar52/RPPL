<?php

/**
 * What is still English-only in the admin panel, and what is wrong with its Hindi. Fast (reads the source,
 * no database), so run it while translating an area:
 *
 *   php tests/Support/admin-i18n-report.php                    everything
 *   php tests/Support/admin-i18n-report.php admin/scoring      only files whose path contains this
 *   php tests/Support/admin-i18n-report.php admin/scoring -v   list every string (default: the first 15 per file)
 *
 * It prints, per file, the visible English that is not inside __() or t(); then the phrases used through
 * __() / t() that have no Hindi in lang/admin/<area>/hi.json; then placeholder mismatches and conflicts.
 * The same checks run in tests/Feature/Admin/AdminTranslationCoverageTest.php.
 */

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Tests\Support\AdminI18n;

$arguments = array_slice($argv, 1);
$verbose = in_array('-v', $arguments, true);
$only = collect($arguments)->first(fn ($a) => $a !== '-v');

$unwrapped = AdminI18n::unwrapped($only);
$count = array_sum(array_map('count', $unwrapped));
echo "== Visible English that is not translated: $count in ".count($unwrapped)." file(s)\n";
foreach ($unwrapped as $file => $strings) {
    echo "\n$file (".count($strings).")\n";
    foreach (array_slice($strings, 0, $verbose ? PHP_INT_MAX : 15) as $string) {
        echo '  - '.$string."\n";
    }
    if (! $verbose && count($strings) > 15) {
        echo '  ... +'.(count($strings) - 15)." more (use -v)\n";
    }
}

$missing = AdminI18n::missing($only);
echo "\n== Phrases used through __() / t() with no Hindi: ".count($missing)."\n";
foreach (array_slice($missing, 0, $verbose ? PHP_INT_MAX : 40, true) as $phrase => $files) {
    echo '  - '.$phrase.'   ['.implode(', ', array_slice($files, 0, 2))."]\n";
}

$mismatches = AdminI18n::mismatches();
echo "\n== Placeholder mismatches: ".count($mismatches)."\n";
foreach ($mismatches as $line) {
    echo "  - $line\n";
}

$conflicts = AdminI18n::conflicts();
echo "\n== Phrases translated two different ways: ".count($conflicts)."\n";
foreach ($conflicts as $line) {
    echo "  - $line\n";
}

exit($count + count($missing) + count($mismatches) + count($conflicts) > 0 ? 1 : 0);
