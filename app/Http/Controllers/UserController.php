<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\SendUserInvitation;
use App\Enums\Role;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => $this->present($user));

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => Role::options(),
        ]);
    }

    public function store(StoreUserRequest $request, SendUserInvitation $sendInvitation): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'password' => null,
        ]);

        $sendInvitation($user, $request->user());

        return back()->with('status', 'invitation-sent');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role.',
            ]);
        }

        $user->update(['role' => $request->validated('role')]);

        return back()->with('status', 'user-updated');
    }

    public function resend(Request $request, User $user, SendUserInvitation $sendInvitation): RedirectResponse
    {
        if (! $user->hasPendingInvitation()) {
            throw ValidationException::withMessages([
                'user' => 'This user has already accepted their invitation.',
            ]);
        }

        $sendInvitation($user, $request->user());

        return back()->with('status', 'invitation-sent');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot remove your own account.',
            ]);
        }

        $user->delete();

        return back()->with('status', 'user-removed');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'status' => $user->hasPendingInvitation() ? 'pending' : 'active',
            'invited_at' => $user->invited_at?->toIso8601String(),
            'invitation_accepted_at' => $user->invitation_accepted_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
