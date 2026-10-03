<?php

namespace App\Services\DataCleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Finds and removes ORPHANED uploaded media files on the public disk: a
 * file that sits in one of the known upload directories but that no
 * database row references any more (e.g. its record was deleted by hand
 * or an earlier cleanup of the record failed half way).
 *
 * It never deletes business data and never judges content: an inactive,
 * old, unpublished or low-priority record still references its file, so
 * that file is not an orphan.
 *
 * Safety model:
 *  - The filesystem side is bounded: only the files directly inside the
 *    directories listed in CATEGORIES are ever considered (no recursion,
 *    no paths taken from the database or from a request). The delete
 *    action re-scans at delete time and acts on what it finds, never on a
 *    list the browser supplies.
 *  - A file counts as referenced if ANY row in ANY category's column (plus
 *    users.photo_path) points at it, so a file shared by several records,
 *    or referenced from another module's column, is kept.
 *  - Upload services store the file BEFORE writing the database row, so a
 *    file uploaded a moment ago can look orphaned while its request is
 *    still in flight. Files younger than MIN_AGE_HOURS are therefore
 *    never candidates.
 *  - Only files whose names look like generated upload names are
 *    candidates; anything else (odd characters, a symlink, a nested
 *    directory) is skipped and reported, never deleted.
 *
 * Deliberately NOT covered: branding/ (the logo and favicon are
 * referenced through settings, so a mistake would blank the site's
 * identity) and the private player-registration documents (sensitive, with
 * their own dedicated cleanup).
 */
class MediaFileCleanupService
{
    public const DISK = 'public';

    /**
     * Files newer than this are never treated as orphans — see the class
     * docblock.
     */
    public const MIN_AGE_HOURS = 24;

    /**
     * key => label, directory (relative to the public disk, no trailing
     * slash), and the table/column that references files in it.
     */
    public const CATEGORIES = [
        'videos' => ['label' => 'Videos', 'directory' => 'videos', 'table' => 'videos', 'column' => 'video_path'],
        'video-thumbnails' => ['label' => 'Video thumbnails', 'directory' => 'videos/thumbnails', 'table' => 'videos', 'column' => 'thumbnail_path'],
        'ad-media' => ['label' => 'Advertisements', 'directory' => 'ads', 'table' => 'advertisements', 'column' => 'media_path'],
        'ad-posters' => ['label' => 'Advertisement previews', 'directory' => 'ads/posters', 'table' => 'advertisements', 'column' => 'poster_path'],
        'photos' => ['label' => 'Photos', 'directory' => 'photos', 'table' => 'photos', 'column' => 'photo_path'],
        'news-images' => ['label' => 'News images', 'directory' => 'news', 'table' => 'news_images', 'column' => 'image_path'],
        'rule-images' => ['label' => 'Rules images', 'directory' => 'rules', 'table' => 'rules', 'column' => 'image_path'],
        'player-photos' => ['label' => 'Player photos', 'directory' => 'players', 'table' => 'players', 'column' => 'photo_path'],
        'team-logos' => ['label' => 'Team logos', 'directory' => 'teams', 'table' => 'teams', 'column' => 'logo_path'],
        'contributor-photos' => ['label' => 'Contributor photos', 'directory' => 'contributors', 'table' => 'contributors', 'column' => 'photo_path'],
    ];

    /**
     * Columns outside CATEGORIES that can also hold a public-disk path.
     * Only used to protect a file from being called an orphan.
     */
    private const EXTRA_REFERENCE_COLUMNS = [
        ['table' => 'users', 'column' => 'photo_path'],
    ];

    public function has(string $category): bool
    {
        return isset(self::CATEGORIES[$category]);
    }

    /**
     * Read-only scan of every category (one set of reference queries,
     * shared by all of them).
     *
     * @return array<string, array{label: string, directory: string, scanned: int, orphans: list<string>, recent: int, skipped: int}>
     */
    public function scanAll(): array
    {
        $referenced = $this->referencedPaths();
        $results = [];

        foreach (self::CATEGORIES as $key => $definition) {
            $results[$key] = $this->scanDirectory($definition, $referenced);
        }

        return $results;
    }

    /**
     * @return array{label: string, directory: string, scanned: int, orphans: list<string>, recent: int, skipped: int}
     */
    public function scan(string $category): array
    {
        return $this->scanDirectory(self::CATEGORIES[$category], $this->referencedPaths());
    }

    /**
     * Re-scans and deletes the orphans found right now, one file at a time
     * so one failure never stops the rest.
     *
     * @return array{scanned: int, orphans: int, deleted: int, failed: int, recent: int, skipped: int}
     */
    public function deleteOrphans(string $category): array
    {
        $scan = $this->scan($category);
        $deleted = 0;
        $failed = 0;

        foreach ($scan['orphans'] as $path) {
            try {
                // delete() returns false for a missing file and true
                // otherwise; a file that vanished since the scan is
                // already clean, not a failure.
                if (Storage::disk(self::DISK)->exists($path)) {
                    Storage::disk(self::DISK)->delete($path) ? $deleted++ : $failed++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return [
            'scanned' => $scan['scanned'],
            'orphans' => count($scan['orphans']),
            'deleted' => $deleted,
            'failed' => $failed,
            'recent' => $scan['recent'],
            'skipped' => $scan['skipped'],
        ];
    }

    /**
     * @param  array{label: string, directory: string, table: string, column: string}  $definition
     * @param  array<string, true>  $referenced
     * @return array{label: string, directory: string, scanned: int, orphans: list<string>, recent: int, skipped: int}
     */
    private function scanDirectory(array $definition, array $referenced): array
    {
        $disk = Storage::disk(self::DISK);
        $directory = $definition['directory'];
        $cutoff = now()->subHours(self::MIN_AGE_HOURS)->getTimestamp();

        $result = [
            'label' => $definition['label'],
            'directory' => $directory,
            'scanned' => 0,
            'orphans' => [],
            'recent' => 0,
            'skipped' => 0,
        ];

        try {
            // files() is non-recursive: videos/ does not include
            // videos/thumbnails/, and nested folders are ignored.
            $files = $disk->files($directory);
        } catch (Throwable $e) {
            report($e);

            return $result;
        }

        foreach ($files as $path) {
            $result['scanned']++;

            if (! $this->looksLikeGeneratedUpload($directory, $path)) {
                $result['skipped']++;

                continue;
            }

            if (isset($referenced[$this->normalize($path)])) {
                continue;
            }

            try {
                if ($disk->lastModified($path) > $cutoff) {
                    $result['recent']++;

                    continue;
                }
            } catch (Throwable $e) {
                // Cannot prove it is old enough: leave it alone.
                $result['skipped']++;

                continue;
            }

            $result['orphans'][] = $path;
        }

        return $result;
    }

    /**
     * Only a plain file directly inside the directory whose name is what
     * UploadedFile::store() generates (letters, digits, dot, dash,
     * underscore) and that is not a symlink.
     */
    private function looksLikeGeneratedUpload(string $directory, string $path): bool
    {
        if (! preg_match('#^'.preg_quote($directory, '#').'/[A-Za-z0-9._-]+$#', $path) || str_contains($path, '..')) {
            return false;
        }

        try {
            $absolute = Storage::disk(self::DISK)->path($path);

            return ! is_link($absolute);
        } catch (Throwable) {
            // A disk with no local path (not the case for 'public').
            return true;
        }
    }

    /**
     * Every path any row references, as a lookup set. Only the path
     * columns are read, never whole models.
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $columns = array_merge(
            array_map(fn (array $c) => ['table' => $c['table'], 'column' => $c['column']], array_values(self::CATEGORIES)),
            self::EXTRA_REFERENCE_COLUMNS,
        );

        $set = [];

        foreach ($columns as $definition) {
            // Paged by the unique id (not the path column) so ties can
            // never cause a row to be skipped.
            DB::table($definition['table'])
                ->whereNotNull($definition['column'])
                ->select('id', $definition['column'])
                ->chunkById(1000, function ($rows) use (&$set, $definition) {
                    foreach ($rows as $row) {
                        $set[$this->normalize((string) $row->{$definition['column']})] = true;
                    }
                });
        }

        return $set;
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }
}
