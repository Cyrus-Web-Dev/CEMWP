<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.manage');
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $like = '%'.addcslashes($q, '%_\\').'%';

        return Inertia::render('ActivityLog/Index', [
            'logs' => ActivityLog::query()
                ->with('user:id,name')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('description', 'ilike', $like)->orWhere('action', 'ilike', $like)))
                ->orderByDesc('id')
                ->paginate(40)->withQueryString(),
            'filters' => ['q' => $q],
        ]);
    }
}
