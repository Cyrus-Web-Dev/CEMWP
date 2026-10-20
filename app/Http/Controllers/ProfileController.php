<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('Profile/Edit', [
            'mustChange' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $request->user()->update($data);

        return back()->with('success', 'Profil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.']);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $user->tokens()->delete(); // token API lama dicabut
        ActivityLogger::log('password.changed', "Password diganti: {$user->name}", null, $user->id);

        return back()->with('success', 'Password berhasil diganti.');
    }
}
