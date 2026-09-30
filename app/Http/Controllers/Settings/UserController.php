<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

        return Inertia::render('settings/users', [
            'users' => User::query()->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']),
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $r) => ['value' => $r->value, 'label' => $r->label()])->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User([
            ...$request->safe()->only(['name', 'email', 'password', 'role']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        // Admin-created accounts skip the email verification step.
        $user->email_verified_at = now();
        $user->save();

        ActivityLog::record('user.created', $user, "Created user {$user->name} ({$user->role->label()})");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'role']);
        $isActive = $request->boolean('is_active');
        $staysAdmin = $data['role'] === UserRole::Admin->value && $isActive;

        if ($user->is($request->user()) && ! $staysAdmin) {
            return back()->withErrors(['role' => 'You cannot remove your own admin access or deactivate yourself.']);
        }

        if ($user->role === UserRole::Admin && ! $staysAdmin && ! $this->otherActiveAdminExists($user)) {
            return back()->withErrors(['role' => 'At least one active admin is required.']);
        }

        $user->fill([...$data, 'is_active' => $isActive]);

        if ($request->filled('password')) {
            $user->password = $request->string('password')->toString();
        }

        $user->save();

        ActivityLog::record('user.updated', $user, "Updated user {$user->name}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

        if ($user->is($request->user())) {
            return back()->withErrors(['role' => 'You cannot delete your own account.']);
        }

        if ($user->role === UserRole::Admin && ! $this->otherActiveAdminExists($user)) {
            return back()->withErrors(['role' => 'At least one active admin is required.']);
        }

        ActivityLog::record('user.deleted', $user, "Deleted user {$user->name}");
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return back();
    }

    protected function otherActiveAdminExists(User $user): bool
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();
    }
}
