<?php

namespace App\Http\Controllers;

use App\Http\Requests\PushSubscriptionRequest;
use App\Notifications\PushTestNotification;
use App\Services\WebPush\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints the service-worker bootstrap talks to when the user turns
 * push notifications on or off for the current device.
 */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->updatePushSubscription(
            $request->string('endpoint')->value(),
            $request->string('keys.p256dh')->value(),
            $request->string('keys.auth')->value(),
            $request->userAgent(),
        );

        return response()->json(['status' => 'subscribed'], 201);
    }

    /**
     * Forget one device. Unsubscribing in the browser only stops delivery
     * there, so the row has to go too or we keep pushing into the void.
     */
    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->string('endpoint')->value();

        if ($endpoint !== '') {
            $request->user()->pushSubscriptions()
                ->where('endpoint_hash', hash('sha256', $endpoint))
                ->delete();
        }

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * Send a test push to the current user, so they can confirm the
     * permission actually works on this device.
     */
    public function test(Request $request, WebPushSender $sender): JsonResponse
    {
        if (! $sender->isEnabled()) {
            return response()->json(['status' => 'disabled'], 503);
        }

        $request->user()->notify(new PushTestNotification);

        return response()->json(['status' => 'sent']);
    }
}
