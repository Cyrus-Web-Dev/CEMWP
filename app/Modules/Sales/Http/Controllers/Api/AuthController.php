<?php

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Services\CustomerAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Auth pelanggan aplikasi mobile (token Sanctum). Pendaftaran & reset password memakai OTP email. */
class AuthController extends ApiBase
{
    public function __construct(private CustomerAuthService $auth)
    {
    }

    public function registerStart(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::defaults()], 'marketing_consent' => ['nullable', 'boolean'],
        ]);

        return $this->guard(function () use ($data) {
            $note = $this->auth->startRegistration($data['name'], $data['email'], $data['phone'] ?? null, $data['password'], (bool) ($data['marketing_consent'] ?? false));

            return response()->json(['message' => 'Kode verifikasi dikirim ke email.', 'note' => $note], 202);
        });
    }

    public function registerVerify(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'string', 'max:10']]);

        return $this->guard(fn () => $this->tokenResponse($this->auth->completeRegistration($data['email'], $data['code']), 201));
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        return $this->guard(fn () => $this->tokenResponse($this->auth->attempt($data['email'], $data['password'])));
    }

    public function resetStart(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        return $this->guard(function () use ($data) {
            $this->auth->startReset($data['email']);

            return response()->json(['message' => 'Bila email terdaftar, kode verifikasi sudah dikirim.'], 202);
        });
    }

    public function resetVerify(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'string', 'max:10'], 'password' => ['required', Password::defaults()]]);

        return $this->guard(function () use ($data) {
            $this->auth->completeReset($data['email'], $data['code'], $data['password']);

            return response()->json(['message' => 'Password berhasil diganti. Silakan masuk.']);
        });
    }

    public function logout(Request $request)
    {
        $request->user('sanctum')->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $request)
    {
        /** @var Customer $c */
        $c = $request->user('sanctum');

        return response()->json(['customer' => $this->customerArray($c) + ['orders_count' => $c->orders_count]]);
    }
}
