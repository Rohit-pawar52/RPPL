<?php

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationSendService;
use Throwable;

/**
 * The auction's push notifications, through the existing Notification /
 * NotificationSend / SendNotificationJob pipeline (the same one match
 * results use). Three kinds, all optional per auction:
 *
 *  - "the auction has started" and "the auction is over" (notify_start);
 *  - "<player> sold to <team> for <points>" — only for sales of at least
 *    notify_sale_min points, and never when that is empty, so subscribers
 *    are not flooded with a message for every player.
 *
 * A notification must never get in the way of the auction: any failure is
 * reported and swallowed. Called from the controllers, which know who is
 * acting (a notification records who created and sent it).
 */
class AuctionNotificationService
{
    public function __construct(private readonly NotificationSendService $sends) {}

    public function started(Auction $auction, User $by): void
    {
        if (! $auction->notify_start) {
            return;
        }

        $this->push($by, 'RPPL Player Auction', 'The player auction has started — follow it live.');
    }

    public function completed(Auction $auction, User $by): void
    {
        if (! $auction->notify_start) {
            return;
        }

        $this->push($by, 'RPPL Player Auction', 'The player auction is over — see who bought whom.');
    }

    public function sold(Auction $auction, AuctionLot $lot, User $by): void
    {
        $minimum = $auction->notify_sale_min;
        $lot->loadMissing('playerRegistration.player', 'teamPlayer.editionTeam.team');

        if ($minimum === null || $lot->current_bid === null || $lot->current_bid < $minimum || ! $lot->teamPlayer) {
            return;
        }

        $this->push(
            $by,
            'RPPL Player Auction',
            $lot->teamPlayer->editionTeam->team->name.' bought '.$lot->playerRegistration->player->name.' for '.points($lot->current_bid).' points.',
        );
    }

    private function push(User $by, string $title, string $message): void
    {
        try {
            $notification = Notification::create([
                'title' => $title,
                'message' => $message,
                'action_url' => route('public.auction.show', absolute: false),
                'created_by' => $by->id,
            ]);

            $this->sends->send($notification, $by);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
