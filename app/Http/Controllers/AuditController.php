<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::AUDIT_VIEW)) {
            abort(403, 'You are not authorised to view audit logs.');
        }

        $query = AuditLog::query();

        if ($search = $request->string('search')->toString()) {
            $query->search($search);
        }
        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }
        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'action', 'from', 'to']),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
