<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Models\Coupon;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CouponController extends SalesController
{
    public function __construct()
    {
        $this->middleware('permission:coupons.manage');
    }

    public function index()
    {
        return Inertia::render('Sales/Coupons/Index', [
            'coupons' => Coupon::query()->with('customer:id,name')->orderByDesc('id')->paginate(25),
        ]);
    }

    public function store(Request $request)
    {
        $coupon = Coupon::create($this->validated($request));
        ActivityLogger::log('coupon.created', "Kupon {$coupon->code} dibuat", $coupon);

        return back()->with('success', 'Kupon dibuat.');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request, $coupon));
        ActivityLogger::log('coupon.updated', "Kupon {$coupon->code} diperbarui", $coupon);

        return back()->with('success', 'Kupon diperbarui.');
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->used_count > 0) {
            return back()->with('error', 'Kupon sudah pernah dipakai, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }
        $coupon->delete();
        ActivityLogger::log('coupon.deleted', "Kupon {$coupon->code} dihapus", null);

        return back()->with('success', 'Kupon dihapus.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => Coupon::normalizeCode((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:100000000000'],
            'min_subtotal' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'gt:0'],
            'channel' => ['required', Rule::in(['all', 'pos', 'online'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['type'] === 'percent' && $data['value'] > 100) {
            abort(422, 'Persen maksimal 100.');
        }

        return $data;
    }
}
