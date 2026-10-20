<?php

namespace App\Modules\Sales\Http\Controllers\Shop;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Services\CustomerAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Login / daftar / reset password pelanggan etalase (verifikasi email lewat OTP). */
class AuthController extends ShopBase
{
    public function __construct(private CustomerAuthService $auth)
    {
    }

    public function show(Request $request)
    {
        if ($this->customer()) {
            return redirect()->route('shop.account');
        }

        return Inertia::render('Shop/Auth', ['pending' => $request->session()->get('shop.auth_pending')]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean']]);

        try {
            $customer = $this->auth->attempt($data['email'], $data['password']);
        } catch (SalesException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        auth('customer')->login($customer, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('shop.account'));
    }

    public function logout(Request $request)
    {
        auth('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('shop.home');
    }

    public function registerStart(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'], 'password' => ['required', 'confirmed', Password::defaults()],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(
            fn () => $this->auth->startRegistration($data['name'], $data['email'], $data['phone'] ?? null, $data['password'], (bool) ($data['marketing_consent'] ?? false)),
            'Kode verifikasi dikirim ke emailmu.',
            function ($note) use ($request, $data) {
                $request->session()->put('shop.auth_pending', ['mode' => 'register', 'email' => strtolower($data['email'])]);

                return back()->with('note', $note);
            }
        );
    }

    public function registerVerify(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'string', 'max:10']]);

        try {
            $customer = $this->auth->completeRegistration($data['email'], $data['code']);
        } catch (SalesException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget('shop.auth_pending');
        auth('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('shop.account')->with('success', 'Akun berhasil dibuat. Selamat datang!');
    }

    public function resetStart(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            $note = $this->auth->startReset($data['email']);
        } catch (SalesException $e) {
            return back()->with('error', $e->getMessage());
        }
        $request->session()->put('shop.auth_pending', ['mode' => 'reset', 'email' => strtolower($data['email'])]);

        // Pesan sama untuk email terdaftar maupun tidak.
        return back()->with('success', 'Bila email terdaftar, kode verifikasi sudah dikirim.')->with('note', $note);
    }

    public function resetVerify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'], 'code' => ['required', 'string', 'max:10'], 'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $this->auth->completeReset($data['email'], $data['code'], $data['password']);
        } catch (SalesException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }
        $request->session()->forget('shop.auth_pending');

        return redirect()->route('shop.auth')->with('success', 'Password berhasil diganti. Silakan masuk.');
    }
}
