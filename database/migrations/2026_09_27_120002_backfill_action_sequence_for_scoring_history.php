<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase S02 rule 42 — one-time backfill so every innings that already
 * has scoring history keeps a correctly continued action_sequence: every
 * pre-existing Delivery/ScoringEvent row for an innings is numbered in
 * created_at order (delivery_sequence as the tiebreak among deliveries
 * sharing the same second; id as the tiebreak among scoring_events
 * sharing the same second; a delivery sorts before a scoring_event
 * sharing the exact same second, since a between-balls correction event
 * realistically follows the ball it reacts to). Going forward, every new
 * row computes its own action_sequence as max(existing)+1 under the same
 * Innings row lock every scoring write already takes, so this backfill
 * only ever needs to run once, here, over historical data — never
 * touches DeliveryService/ScoringEventService application logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        $inningsIds = DB::table('deliveries')->distinct()->pluck('innings_id')
            ->merge(DB::table('scoring_events')->whereNotNull('innings_id')->distinct()->pluck('innings_id'))
            ->unique()
            ->values();

        foreach ($inningsIds as $inningsId) {
            $deliveries = DB::table('deliveries')
                ->where('innings_id', $inningsId)
                ->orderBy('delivery_sequence')
                ->get(['id', 'created_at', 'delivery_sequence']);

            $events = DB::table('scoring_events')
                ->where('innings_id', $inningsId)
                ->orderBy('id')
                ->get(['id', 'created_at']);

            $actions = $deliveries->map(fn ($d) => ['table' => 'deliveries', 'id' => $d->id, 'created_at' => $d->created_at, 'tiebreak' => 0])
                ->merge($events->map(fn ($e) => ['table' => 'scoring_events', 'id' => $e->id, 'created_at' => $e->created_at, 'tiebreak' => 1]))
                ->sort(fn ($a, $b) => [$a['created_at'], $a['tiebreak']] <=> [$b['created_at'], $b['tiebreak']])
                ->values();

            $sequence = 1;

            foreach ($actions as $action) {
                DB::table($action['table'])->where('id', $action['id'])->update(['action_sequence' => $sequence]);
                $sequence++;
            }
        }
    }

    public function down(): void
    {
        // Columns themselves are dropped by the migrations that added
        // them; nothing to reverse here beyond that.
    }
};
