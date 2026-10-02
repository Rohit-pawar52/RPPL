<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A stored datetime is UTC. Printing it straight from the model shows UTC —
 * for India that is 5 hours 30 minutes behind, and between midnight and
 * 5:30 AM it is even the wrong DATE. Every datetime shown to a person (a
 * view, a CSV export) therefore goes through display_datetime() /
 * DisplayTimezoneFormatter, which convert to system.display_timezone.
 *
 * This keeps the next view or export from quietly reintroducing the
 * mistake: it fails, naming the file and line, for any "*_at" attribute
 * formatted directly, and for the current time formatted directly.
 */
class DisplayTimezoneConventionTest extends TestCase
{
    /**
     * Columns named "*_at" that are DATE columns (a calendar day with no
     * time of day), so there is nothing to convert.
     */
    private const DATE_ONLY = ['contributed_at'];

    public function test_no_view_or_export_formats_a_stored_datetime_without_the_display_timezone(): void
    {
        $offenders = [];

        foreach ($this->phpAndBladeFiles() as $path => $relative) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
                if ($this->formatsStoredDatetime($line) || $this->formatsCurrentTime($line)) {
                    $offenders[] = sprintf('%s:%d  %s', $relative, $index + 1, trim($line));
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Use display_datetime() (views) or DisplayTimezoneFormatter::format() (code) instead of formatting a UTC value directly:\n".implode("\n", $offenders),
        );
    }

    private function formatsStoredDatetime(string $line): bool
    {
        if (! preg_match_all('/\b([A-Za-z_]+)\??->format\(/', $line, $matches)) {
            return false;
        }

        foreach ($matches[1] as $attribute) {
            if ($attribute === 'generatedAt' || (str_ends_with($attribute, '_at') && ! in_array($attribute, self::DATE_ONLY, true))) {
                return true;
            }
        }

        return false;
    }

    private function formatsCurrentTime(string $line): bool
    {
        return str_contains($line, 'now()->format(') || str_contains($line, 'now()->year');
    }

    /**
     * Blade views plus the controllers/services that build exports, as
     * [absolute path => path relative to the project].
     *
     * @return array<string, string>
     */
    private function phpAndBladeFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        foreach (['resources/views', 'app'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $path = str_replace('\\', '/', $file->getPathname());
                    $files[$path] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
                }
            }
        }

        return $files;
    }
}
