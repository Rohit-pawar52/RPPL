<?php

namespace Tests\Feature\Seeders;

use App\Models\Announcement;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionTransaction;
use App\Models\News;
use App\Models\NewsImage;
use App\Models\Photo;
use App\Models\PlayerRegistration;
use App\Models\Rule;
use Database\Seeders\Demo\DemoAnnouncementSeeder;
use Database\Seeders\Demo\DemoNewsSeeder;
use Database\Seeders\Demo\DemoPhotoSeeder;
use Database\Seeders\Demo\DemoRuleSeeder;
use Database\Seeders\Demo\DemoSettingSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Rppl2025\Rppl2025EditionSeeder;
use Database\Seeders\Rppl2025\Rppl2025FinanceSeeder;
use Database\Seeders\Rppl2026\Rppl2026EditionSeeder;
use Database\Seeders\Rppl2026\Rppl2026ExtraRegistrationSeeder;
use Database\Seeders\Rppl2026\Rppl2026FixtureSeeder;
use Database\Seeders\Rppl2026\Rppl2026PlayerSeeder;
use Database\Seeders\Rppl2026\Rppl2026RegistrationAndSquadSeeder;
use Database\Seeders\Rppl2026\Rppl2026TeamAndVenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proves the demo content/media/finance/registration seeders produce
 * coherent, reference-safe, idempotent data on top of the core dataset.
 */
class DemoContentSeederTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT_SEEDERS = [
        DemoRuleSeeder::class,
        DemoAnnouncementSeeder::class,
        Rppl2026ExtraRegistrationSeeder::class,
        Rppl2025FinanceSeeder::class,
        DemoNewsSeeder::class,
        DemoPhotoSeeder::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([
            RoleSeeder::class,
            DemoSettingSeeder::class,
            DemoUserSeeder::class,
            Rppl2026PlayerSeeder::class,
            Rppl2026TeamAndVenueSeeder::class,
            Rppl2026EditionSeeder::class,
            Rppl2026RegistrationAndSquadSeeder::class,
            Rppl2026FixtureSeeder::class,
            Rppl2025EditionSeeder::class,
        ]);
    }

    public function test_content_seeders_build_coherent_data_and_are_idempotent(): void
    {
        $this->seed(self::CONTENT_SEEDERS);

        $this->assertNewsAndMediaAreConsistent();
        $this->assertFinanceOf2025();
        $this->assertExtraRegistrations();
        $this->assertLiveTickerUnchanged();

        $counts = $this->counts();
        $this->seed(self::CONTENT_SEEDERS);
        $this->assertSame($counts, $this->counts(), 'Re-running the seeders must not create duplicates.');
    }

    private function assertNewsAndMediaAreConsistent(): void
    {
        $this->assertGreaterThanOrEqual(6, News::count());
        $this->assertSame(News::count(), News::pluck('slug')->unique()->count());

        $visible = News::visible()->get();
        $this->assertTrue($visible->isNotEmpty());
        $this->assertTrue($visible->every(fn (News $n) => $n->status === 'active' && $n->published_at->lte(now())));
        $this->assertTrue(News::where('status', 'inactive')->exists());
        $this->assertFalse($visible->contains(fn (News $n) => $n->status === 'inactive'));

        $this->assertGreaterThan(0, NewsImage::count());
        foreach (NewsImage::all() as $image) {
            Storage::disk('public')->assertExists($image->image_path);
        }

        $this->assertSame(4, Photo::count());
        foreach (Photo::all() as $photo) {
            Storage::disk('public')->assertExists($photo->photo_path);
        }
    }

    private function assertFinanceOf2025(): void
    {
        $edition = Edition::where('year', 2025)->firstOrFail();
        $transactions = EditionTransaction::where('edition_id', $edition->id)->get();

        $income = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');
        $this->assertGreaterThan($expense, $income);
        $this->assertTrue($transactions->every(fn ($t) => $t->transaction_date->year === 2025));

        // Every committee member has paid at least the full 1000 due.
        $members = EditionCommitteeMember::where('edition_id', $edition->id)->get();
        $this->assertCount(3, $members);
        foreach ($members as $member) {
            $paid = $member->contributor->contributions()->where('edition_id', $edition->id)->sum('amount');
            $this->assertGreaterThanOrEqual(1000, (float) $paid);
        }
    }

    private function assertExtraRegistrations(): void
    {
        $edition = Edition::where('year', 2026)->firstOrFail();
        $extras = PlayerRegistration::where('edition_id', $edition->id)->where('payment_status', '!=', 'paid')->get();

        $this->assertSame(
            ['failed' => 1, 'pending' => 2, 'refunded' => 1],
            $extras->countBy('payment_status')->sortKeys()->all()
        );
        $this->assertTrue($extras->every(fn ($r) => ! $r->teamPlayer()->exists()));
        $this->assertTrue($extras->every(fn ($r) => $r->ocr_status === PlayerRegistration::OCR_FAILED));
        $this->assertSame(60, PlayerRegistration::where('edition_id', $edition->id)->where('payment_status', 'paid')->count());
    }

    private function assertLiveTickerUnchanged(): void
    {
        $this->assertSame(3, Announcement::active()->count());
        $this->assertSame(
            ['disabled', 'expired'],
            Announcement::where('sort_order', '>=', 10)->get()->map->computedStatus()->sort()->values()->all()
        );
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'news' => News::count(),
            'news_images' => NewsImage::count(),
            'photos' => Photo::count(),
            'announcements' => Announcement::count(),
            'registrations' => PlayerRegistration::count(),
            'transactions' => EditionTransaction::count(),
            'rules' => Rule::count(),
        ];
    }
}
