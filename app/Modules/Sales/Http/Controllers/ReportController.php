<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends SalesController
{
    public function __construct(private ReportService $reports)
    {
        $this->middleware('permission:reports.view');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'channel' => ['nullable', 'in:pos,ecommerce'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return Inertia::render('Sales/Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'channel' => $data['channel'] ?? ''],
            'report' => $this->reports->summary($from, $to, $data['channel'] ?? null),
        ]);
    }
}
