<?php

namespace Tests\Unit\Events;

use App\Events\MatchScoreUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3.37B1 — the event contract only. No dispatch, no queue, no
 * live Reverb server involved; this simply proves the class is built
 * exactly per the invalidation-signal design: queued (never "now"),
 * transaction-safe, one public channel scoped by match id, a stable
 * frontend-facing name, and a payload that is exactly {match_id} —
 * nothing that could leak a model or private data.
 */
class MatchScoreUpdatedTest extends TestCase
{
    public function test_it_implements_should_broadcast_and_not_should_broadcast_now(): void
    {
        $event = new MatchScoreUpdated(42);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertNotInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_after_commit_is_true(): void
    {
        $event = new MatchScoreUpdated(42);

        $this->assertTrue($event->afterCommit);
    }

    public function test_it_broadcasts_on_exactly_one_public_channel_scoped_by_match_id(): void
    {
        $event = new MatchScoreUpdated(42);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertNotInstanceOf(PresenceChannel::class, $channels[0]);
        $this->assertSame('public-match.42', $channels[0]->name);
    }

    public function test_broadcast_name_is_stable_and_namespace_independent(): void
    {
        $event = new MatchScoreUpdated(42);

        $this->assertSame('match.score.updated', $event->broadcastAs());
    }

    public function test_broadcast_payload_is_exactly_match_id_and_nothing_else(): void
    {
        $event = new MatchScoreUpdated(42);

        $this->assertSame(['match_id' => 42], $event->broadcastWith());
    }

    /**
     * The event's only public state is the primitive match id — no
     * GameMatch/Innings/Delivery/User model or other property exists to
     * accidentally serialize/expose. Reflection is used here (rather
     * than just re-checking broadcastWith()) specifically to catch a
     * future accidental `public GameMatch $match` property that
     * broadcastWith() might still correctly avoid returning but that
     * Laravel's default event serialization could otherwise expose.
     */
    public function test_event_exposes_no_model_only_the_primitive_match_id(): void
    {
        $event = new MatchScoreUpdated(42);

        $properties = (new \ReflectionClass($event))->getProperties(\ReflectionProperty::IS_PUBLIC);
        $propertyNames = array_map(fn (\ReflectionProperty $property) => $property->getName(), $properties);

        $this->assertSame(['afterCommit', 'matchId'], $propertyNames);
        $this->assertIsInt($event->matchId);
    }
}
