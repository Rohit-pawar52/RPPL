<?php

namespace Database\Seeders\Demo;

use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\News;
use App\Models\NewsImage;
use App\Models\TeamPlayer;
use App\Services\News\NewsService;
use App\Services\Settings\DisplayTimezoneFormatter;
use Database\Seeders\Demo\Support\DemoImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Demo News posts for the 2025 and 2026 seasons. Anything that states a
 * result, a champion, a count or a fixture is derived from the database
 * at seed time — never hard-coded — so the posts can't contradict the
 * data. If the 2025 final (or any 2026 result/fixture) does not exist,
 * the dependent post falls back to a generic wording or is skipped.
 *
 * Run after the 2025/2026 fixture seeders. 2025 posts use absolute April
 * 2025 dates; 2026 posts are dated relative to now. Images are generated
 * placeholders (see DemoImage), attached only when the file exists.
 */
class DemoNewsSeeder extends Seeder
{
    public function __construct(
        private readonly NewsService $news,
        private readonly DisplayTimezoneFormatter $timezone,
    ) {}

    public function run(): void
    {
        $edition2025 = Edition::where('year', 2025)->first();
        $edition2026 = Edition::where('year', 2026)->first();

        $posts = [];

        if ($edition2025) {
            $posts[] = $this->kickOff2025();
            $posts[] = $this->final2025($edition2025);
        }

        if ($edition2026) {
            array_push($posts, ...$this->season2026($edition2026));
        }

        $posts[] = [
            'title' => 'Fair play and code of conduct: a reminder to every team',
            'content' => "RPPL is played in a friendly spirit, and every team and supporter is asked to keep it that way.\n\nAccept the umpires' decisions without argument, respect opponents and volunteers, and keep the ground clean. Captains are responsible for the conduct of their squad on and off the field.\n\nThe tournament committee's decision is final on all disciplinary matters.",
            'priority' => 5,
            'published_at' => now()->subDays(2),
            'image' => null,
        ];

        // A draft-style post that is deliberately not public.
        $posts[] = [
            'title' => 'Draft: volunteer roster for the closing ceremony',
            'content' => "Draft notes for the closing ceremony volunteer roster. To be finalised and published once the fixture list is complete.\n\nNot yet public.",
            'priority' => 9,
            'published_at' => now()->subDays(1),
            'status' => 'inactive',
            'image' => null,
        ];

        foreach ($posts as $post) {
            $this->upsert($post);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function kickOff2025(): array
    {
        return [
            'title' => 'RPPL 2025 kicks off: four teams, one trophy',
            'content' => "The RajaBhoj Pawar Premier League 2025 season gets under way this April with four teams competing for the trophy.\n\nAfter a registration window that ran through February and March, the squads are set and the fixtures are published. Every team will play the others in the league stage before the top sides meet in the final.\n\nSupporters are welcome at all matches. Follow the schedule and live scores right here on the RPPL website.",
            'priority' => 2,
            'published_at' => Carbon::create(2025, 4, 2, 9, 0, 0, 'Asia/Kolkata')->utc(),
            'image' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function final2025(Edition $edition): array
    {
        $final = GameMatch::with('winner.team')
            ->where('edition_id', $edition->id)
            ->where('match_stage', 'final')
            ->where('match_status', 'completed')
            ->whereNotNull('match_result')
            ->first();

        $published = Carbon::create(2025, 4, 27, 21, 0, 0, 'Asia/Kolkata')->utc();

        if ($final && $final->winner) {
            $champion = $final->winner->team->name;

            return [
                'title' => $champion.' are the RPPL 2025 champions',
                'content' => "The RPPL 2025 final is done: {$final->match_result}.\n\n{$champion} lift the trophy at the end of a season that brought four teams and a full league stage together.\n\nThank you to every player, umpire, scorer, volunteer and supporter who made the 2025 season possible. See you in 2026.",
                'priority' => 1,
                'published_at' => $published,
                'image' => 2,
            ];
        }

        return [
            'title' => 'RPPL 2025 season wrap-up',
            'content' => "The RPPL 2025 season has come to a close.\n\nThank you to every player, umpire, scorer, volunteer and supporter who took part. Match results and standings for the season remain available on the website.\n\nPreparations for the next edition are already under way.",
            'priority' => 3,
            'published_at' => $published,
            'image' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function season2026(Edition $edition): array
    {
        $posts = [];

        $teamCount = $edition->editionTeams()->count();
        $squadded = TeamPlayer::whereHas('editionTeam', fn ($q) => $q->where('edition_id', $edition->id))->count();

        if ($teamCount > 0 && $squadded > 0) {
            $posts[] = [
                'title' => 'RPPL 2026 registrations closed and squads announced',
                'content' => "Player registration for RPPL 2026 is now closed, and the squads are announced.\n\n{$teamCount} teams will take part, with {$squadded} registered players placed into squads. Each team's full squad is listed on its team page.\n\nGood luck to all teams for the season ahead.",
                'priority' => 2,
                'published_at' => now()->subDays(14),
                'image' => 3,
            ];
        }

        $firstCompleted = GameMatch::with('teamA.team', 'teamB.team')
            ->where('edition_id', $edition->id)
            ->where('match_status', 'completed')
            ->whereNotNull('match_result')
            ->orderBy('scheduled_at')
            ->first();

        if ($firstCompleted) {
            $a = $firstCompleted->teamA->team->name;
            $b = $firstCompleted->teamB->team->name;

            $posts[] = [
                'title' => "Opening week recap: {$a} vs {$b}",
                'content' => "The RPPL 2026 season began with {$a} taking on {$b}.\n\nResult: {$firstCompleted->match_result}.\n\nFull scorecards, standings and player statistics are available on the match and standings pages.",
                'priority' => 3,
                'published_at' => now()->subDays(8),
                'image' => null,
            ];
        }

        $upcoming = GameMatch::with('teamA.team', 'teamB.team')
            ->where('edition_id', $edition->id)
            ->where('match_status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get();

        if ($upcoming->isNotEmpty()) {
            $lines = $upcoming->map(fn (GameMatch $m) => sprintf(
                '%s vs %s on %s',
                $m->teamA->team->name,
                $m->teamB->team->name,
                $this->timezone->format($m->scheduled_at, 'd M Y, h:i A')
            ))->implode("\n");

            $posts[] = [
                'title' => 'Upcoming RPPL 2026 fixtures',
                'content' => "The next matches on the RPPL 2026 calendar:\n\n{$lines}\n\nTimes are shown in the tournament's local time. Fixtures can change, so check the schedule page for the latest.",
                'priority' => 4,
                'published_at' => now()->subDays(1),
                'image' => null,
            ];
        }

        return $posts;
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function upsert(array $post): void
    {
        $news = News::where('title', $post['title'])->first()
            ?? News::create([
                'title' => $post['title'],
                'slug' => $this->news->uniqueSlug($post['title']),
                'content' => $post['content'],
                'status' => $post['status'] ?? 'active',
                'priority' => $post['priority'],
                'published_at' => $post['published_at'],
            ]);

        if (! empty($post['image']) && ! $news->images()->exists()) {
            $n = $post['image'];
            $path = DemoImage::store("news/demo-placeholder-{$n}.png", $n);

            if (Storage::disk('public')->exists($path)) {
                NewsImage::create(['news_id' => $news->id, 'image_path' => $path, 'sort_order' => 0]);
            }
        }
    }
}
