<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SubscribeFcmTokenRequest;
use App\Services\Notification\FcmTokenSubscriptionService;
use Illuminate\Http\JsonResponse;

/**
 * Anonymous FCM token subscription (Phase B1) — no authentication, no
 * account created for the guest. Kept thin — upsert semantics and
 * concurrency handling live in FcmTokenSubscriptionService, never here.
 * The response is deliberately minimal: never the token itself, the row
 * id, or any ownership field (see the Phase B1 audit report's privacy
 * section) — the frontend only needs to know the call succeeded.
 */
class FcmTokenController extends Controller
{
    public function __construct(private readonly FcmTokenSubscriptionService $subscriptions) {}

    public function store(SubscribeFcmTokenRequest $request): JsonResponse
    {
        $created = $this->subscriptions->subscribe($request->validated('token'));

        return response()->json(['subscribed' => true], $created ? 201 : 200);
    }
}
