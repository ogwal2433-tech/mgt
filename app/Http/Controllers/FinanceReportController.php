<?php

namespace App\Http\Controllers;

use App\Services\FinanceReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceReportController extends Controller
{
    public function __construct(private readonly FinanceReportService $reports) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasAnyPermission([
            \App\Support\Permissions::REPORTS_FINANCIAL,
            \App\Support\Permissions::FINANCE_VIEW,
        ])) {
            abort(403);
        }

        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();
        $direction = $request->string('direction')->toString() ?: null;

        return Inertia::render('Reports/Finance', [
            'filters' => ['from' => $from, 'to' => $to, 'direction' => $direction],
            'summary' => $this->reports->summary($from, $to),
            'cashbook' => $this->reports->cashbook($from, $to, $direction),
            'aging' => $this->reports->aging($request->string('as_of')->toString() ?: null),
            'currency' => \App\Support\Settings::get('finance.currency', 'USD'),
        ]);
    }
}
