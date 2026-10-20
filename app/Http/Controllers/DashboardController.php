<?php

namespace App\Http\Controllers;

use App\Modules\Sales\Services\ReportService;
use App\Modules\Warehouse\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $warehouse, ReportService $sales)
    {
        $canSeeStock = $request->user()->can('inventory.view');

        return Inertia::render('Dashboard', [
            'sales' => $request->user()->can('orders.view') ? $sales->today() : null,
            'canPos' => $request->user()->can('pos.operate'),
            // Modul lain (Sales, CRM, Marketing) menambahkan blok metriknya di sini saat dibangun.
            'warehouse' => $canSeeStock ? [
                'summary' => $warehouse->summary(),
                'lowStock' => $warehouse->lowStockProducts(),
                'recentMovements' => $warehouse->recentMovements(),
            ] : null,
        ]);
    }
}
