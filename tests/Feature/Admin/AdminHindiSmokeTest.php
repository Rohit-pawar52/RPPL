<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A translated view that has a typo (a bad placeholder, an unbalanced quote in a __() call) fails with a 500 only
 * in the language nobody looked at. This opens every admin page that needs no id, as an admin working in Hindi, and
 * checks it still answers and is in Hindi. (Pages with an id are covered by the per-area Hindi tests.)
 */
class AdminHindiSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Pages that download a file or have side effects when opened. */
    // content-pages needs its seeded pages (the test database has none, so it fails in English too).
    private const SKIP = ['export', 'download', 'pdf', 'sample', 'logout', 'preview', 'print', 'content-pages'];

    /**
     * @return list<string>
     */
    private function pagesWithoutAnId(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
            ->filter(fn (string $uri) => str_starts_with($uri, '/admin/') && ! str_contains($uri, '{'))
            ->reject(fn (string $uri) => collect(self::SKIP)->contains(fn ($word) => str_contains($uri, $word)))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function test_every_admin_page_without_an_id_opens_in_hindi(): void
    {
        $admin = User::factory()->create([
            'locale' => 'hi',
            'role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id,
        ]);

        $pages = $this->pagesWithoutAnId();
        $this->assertGreaterThan(30, count($pages), 'The route scan found too few pages to mean anything.');

        $broken = [];
        foreach ($pages as $uri) {
            $response = $this->actingAs($admin)->get($uri);

            if ($response->isRedirection()) {
                continue;
            }

            if ($response->getStatusCode() >= 400 || ! str_contains($response->getContent(), '<html lang="hi"')) {
                $broken[] = $uri.' => '.$response->getStatusCode();
            }
        }

        $this->assertSame([], $broken, 'These admin pages did not open in Hindi:');
    }
}
