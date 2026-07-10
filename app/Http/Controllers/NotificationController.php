<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a single notification (or all) as read.
     */
    public function markRead(Request $request): RedirectResponse
    {
        $id = $request->input('id');

        if ($id) {
            $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);
        } else {
            $request->user()->unreadNotifications->markAsRead();
        }

        return back();
    }
}
