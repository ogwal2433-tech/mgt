<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_SETTINGS)) {
            abort(403);
        }

        return Inertia::render('Settings/Index', [
            'company' => Settings::company(),
            'settings' => \App\Models\SystemSetting::orderBy('group')->orderBy('key')->get(),
            'uploads' => [
                'max_bytes' => (int) Settings::get('uploads.max_bytes', 20480),
            ],
        ]);
    }

    public function update(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_SETTINGS)) {
            abort(403);
        }

        $data = $request->validate([
            'company.name' => ['required', 'string', 'max:255'],
            'company.email' => ['nullable', 'email', 'max:190'],
            'company.phone' => ['nullable', 'string', 'max:60'],
            'company.address' => ['nullable', 'string', 'max:255'],
            'company.website' => ['nullable', 'string', 'max:255'],
            'finance.currency' => ['required', 'string', 'max:10'],
            'app.timezone' => ['required', 'string', 'max:64'],
            'uploads.max_bytes' => ['required', 'integer', 'min:1', 'max:102400'],
            'contracts.reminder_days' => ['nullable', 'string', 'max:60'],
            'workflow.allow_return_for_correction' => ['nullable', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            $type = match (true) {
                $key === 'uploads.max_bytes' => 'integer',
                $key === 'workflow.allow_return_for_correction' => 'boolean',
                default => 'string',
            };
            Settings::set($key, $value, explode('.', $key)[0], $type);
        }

        AuditLogger::log('settings.updated', 'SystemSetting', null, 'Application settings updated', null, $data);

        return back()->with('success', 'Settings saved.');
    }
}
