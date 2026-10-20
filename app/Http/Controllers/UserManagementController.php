<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.view')->only(['index']);
        $this->middleware('permission:users.manage')->except(['index']);
    }

    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()->with('roles:id,name')->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
                'must_change_password' => $u->must_change_password,
                'role' => $u->roles->first()?->name,
                'last_login_at' => $u->last_login_at?->format('Y-m-d H:i'),
            ]),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::exists('roles', 'name')],
        ]);

        $user = User::create([
            'name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'],
            'status' => 'active', 'email_verified_at' => now(), 'must_change_password' => true,
        ]);
        $user->syncRoles([$data['role']]);
        ActivityLogger::log('user.created', "Akun baru: {$user->email} ({$data['role']})", $user);

        return back()->with('success', 'Akun dibuat. Pengguna wajib mengganti password saat login pertama.');
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::exists('roles', 'name')]]);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Kamu tidak bisa mengubah role akunmu sendiri.');
        }
        $user->syncRoles([$data['role']]);
        ActivityLogger::log('user.role', "Role {$user->email} diubah ke {$data['role']}", $user);

        return back()->with('success', 'Role diperbarui.');
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Kamu tidak bisa menonaktifkan akunmu sendiri.');
        }
        $user->update(['status' => $user->isActive() ? 'inactive' : 'active']);
        if (! $user->isActive()) {
            $user->tokens()->delete();
        }
        ActivityLogger::log('user.status', "Akun {$user->email} ".($user->isActive() ? 'diaktifkan' : 'dinonaktifkan'), $user);

        return back()->with('success', $user->isActive() ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', Password::defaults()]]);

        $user->forceFill(['password' => $data['password'], 'must_change_password' => true])->save();
        $user->tokens()->delete();
        ActivityLogger::log('user.password_reset', "Password {$user->email} direset oleh admin", $user);

        return back()->with('success', 'Password sementara dipasang. Pengguna wajib menggantinya saat login.');
    }
}
