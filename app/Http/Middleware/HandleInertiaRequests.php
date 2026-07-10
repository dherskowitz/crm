<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'can' => $this->capabilities($user),
            ],
            'notifications' => fn () => $this->notifications($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The authenticated user's recent unread notifications and unread count,
     * powering the in-app bell. Lazily evaluated so it only runs when needed.
     *
     * @return array{unread: int, items: array<int, array<string, mixed>>}
     */
    private function notifications(?User $user): array
    {
        if (! $user) {
            return ['unread' => 0, 'items' => []];
        }

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Build the capability map for the authenticated user so the UI can
     * hide what the user cannot do. Every recognised capability is present
     * as a boolean.
     *
     * @return array<string, bool>
     */
    private function capabilities(?User $user): array
    {
        return collect(Role::allCapabilities())
            ->mapWithKeys(fn (string $capability) => [
                $capability => (bool) $user?->hasCapability($capability),
            ])
            ->all();
    }
}
