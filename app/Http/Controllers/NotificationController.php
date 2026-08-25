<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-app inbox mirroring what went out over push.
 *
 * Push delivery is best-effort — permission denied, device offline, an
 * expired subscription — so every notification is also stored and shown
 * here. This is the reliable channel; push is the interruption.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /**
     * Follow a notification to whatever it is about, marking it read on
     * the way through.
     */
    public function show(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);

        $record->markAsRead();

        return redirect($record->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'notifications-read');
    }
}
