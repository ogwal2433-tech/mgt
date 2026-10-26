<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['roles:id,name,slug', 'employee:id,employee_code,first_name,last_name,department_id'])
            ->orderBy('username');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($role = $request->string('role')->toString()) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $role));
        }
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return Inertia::render('Users/Index', [
            'users' => $query->paginate(15)->withQueryString(),
            'roles' => Role::with('permissions:id,slug,name')->orderBy('name')->get(),
            'employees' => Employee::orderBy('first_name')->get(['id', 'employee_code', 'first_name', 'last_name']),
            'filters' => $request->only(['search', 'role', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_USERS)) {
            abort(403);
        }

        // By default a new account is activated by emailing the employee a
        // temporary password that must be changed at first sign-in.
        $sendInvitation = (bool) $request->input('send_invitation', true);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => [$sendInvitation ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
            'must_change_password' => ['nullable', 'boolean'],
            'send_invitation' => ['nullable', 'boolean'],
        ]);

        $temporaryPassword = $sendInvitation ? $this->generateTemporaryPassword() : null;

        $user = DB::transaction(function () use ($data, $temporaryPassword, $sendInvitation) {
            $user = User::create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword ?? $data['password']),
                'employee_id' => $data['employee_id'] ?? null,
                'is_active' => true,
                'must_change_password' => $sendInvitation ? true : (bool) ($data['must_change_password'] ?? true),
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync($data['roles']);
            $this->syncEmployeeProfile($user, $data['name'], $data['email']);

            AuditLogger::log('user.created', User::class, $user->id, "Created account {$user->username}");

            return $user;
        });

        if ($sendInvitation) {
            // Email the employee a temporary password straight away.
            $invite = $this->deliverInvitation($user, $temporaryPassword);

            return back()->with('success', "Account created. Invitation emailed to {$user->email}.")->with('invite', $invite);
        }

        NotificationService::notify($user, 'account.created', 'Your account has been created',
            'Your Biiteek account has been created. Sign in with your username and the password provided by your administrator.');

        return back()->with('success', 'User account created.');
    }

    public function update(Request $request, User $user)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_USERS)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'reset_password' => ['nullable', 'string', 'min:8'],
        ], [
            'reset_password.min' => 'The temporary password must be at least 8 characters.',
        ]);

        DB::transaction(function () use ($user, $data, $request) {
            $oldRoles = $user->roles->pluck('slug')->all();

            $update = array_intersect_key($data, array_flip(['name', 'email', 'employee_id', 'is_active']));

            if (array_key_exists('is_active', $data)) {
                $update['is_active'] = (bool) $data['is_active'];
                if (! $update['is_active']) {
                    $update['deactivated_at'] = now();
                } else {
                    $update['deactivated_at'] = null;
                }
            }

            if (! empty($data['reset_password'])) {
                $update['password'] = Hash::make($data['reset_password']);
                $update['must_change_password'] = true;
            }

            $user->update($update);

            if (isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            // Keep the linked employee record in sync so name/email changes
            // are visible everywhere the employee profile is displayed.
            $this->syncEmployeeProfile($user, $data['name'] ?? null, $data['email'] ?? null);

            AuditLogger::log('user.updated', User::class, $user->id, "Updated account {$user->username}",
                ['roles' => $oldRoles, 'is_active' => $user->getOriginal('is_active')],
                ['roles' => $user->roles->pluck('slug')->all(), 'is_active' => $user->is_active]);
        });

        NotificationService::notify($user, 'account.updated', 'Your account was updated',
            'Your account settings were updated by a system administrator.');

        return back()->with('success', 'User account updated.');
    }

    public function permissionsMatrix()
    {
        return Inertia::render('Users/Permissions', [
            'roles' => Role::with('permissions:id,slug,name,group')->orderBy('name')->get(),
            'catalogue' => \App\Support\Permissions::catalogue(),
        ]);
    }

    /**
     * Invite a user by email with a temporary password.
     * The user must change the password at first sign-in.
     */
    public function invite(Request $request, User $user)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_USERS)) {
            abort(403);
        }

        if (! $user->email) {
            return back()->withErrors(['invite' => 'This account has no email address on file.']);
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        $invite = $this->deliverInvitation($user->fresh(), $temporaryPassword);

        return back()->with('success', "Invitation sent to {$user->email}.")->with('invite', $invite);
    }

    /**
     * Email the invitation (temporary password) and record everything.
     *
     * @return array<string, mixed>
     */
    private function deliverInvitation(User $user, string $temporaryPassword): array
    {
        $loginUrl = url('/');

        $delivery = NotificationService::sendMailable(
            $user,
            new \App\Mail\UserInviteMail($user->fresh('roles'), $temporaryPassword, $loginUrl),
            'You are invited to the Biiteek Management System',
            'user_invite',
            User::class,
            $user->id
        );

        AuditLogger::log('user.invited', User::class, $user->id,
            "Invited {$user->username} by email ({$user->email})",
            null,
            ['temporary_password_issued' => true, 'email_delivery' => $delivery->status]);

        NotificationService::notify(
            $user,
            'account.invited',
            'Welcome to Biiteek',
            'Your account has been created. Sign in with your temporary password and set a new one when prompted.',
            '/',
            [],
            false
        );

        return [
            'username' => $user->username,
            'name' => $user->employee?->full_name ?? $user->name,
            'email' => $user->email,
            'temporary_password' => $temporaryPassword,
            'delivery_status' => $delivery->status,
            'login_url' => $loginUrl,
        ];
    }

    /**
     * Suspend an account — the user can no longer sign in, history is preserved.
     */
    public function suspend(Request $request, User $user)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_USERS)) {
            abort(403);
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['status' => 'You cannot suspend your own account.']);
        }

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        AuditLogger::log('user.suspended', User::class, $user->id,
            "Suspended account {$user->username}", ['is_active' => true], ['is_active' => false]);

        NotificationService::notify($user, 'account.suspended', 'Account suspended',
            'Your Biiteek account has been suspended. Contact your administrator if you believe this is a mistake.');

        return back()->with('success', "{$user->username} has been suspended. All records are preserved.");
    }

    /**
     * Renew (reactivate) a suspended account.
     */
    public function renew(Request $request, User $user)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_USERS)) {
            abort(403);
        }

        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        AuditLogger::log('user.renewed', User::class, $user->id,
            "Renewed account {$user->username}", ['is_active' => false], ['is_active' => true]);

        NotificationService::notify($user, 'account.renewed', 'Account access renewed',
            'Your Biiteek account has been reactivated. You can sign in again.');

        return back()->with('success', "{$user->username} can sign in again.");
    }

    /**
     * Email a password-reset link to the user (admin-initiated).
     */
    public function sendResetLink(Request $request, User $user)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_USERS)) {
            abort(403);
        }

        if (! $user->email) {
            return back()->withErrors(['reset' => 'This account has no email address on file.']);
        }

        $status = \Illuminate\Support\Facades\Password::broker()->sendResetLink([
            'email' => $user->email,
        ]);

        if ($status !== \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return back()->withErrors(['reset' => __($status)]);
        }

        AuditLogger::log('user.reset_link_sent', User::class, $user->id,
            "Sent password reset link to {$user->email}");

        return back()->with('success', "Password reset link emailed to {$user->email}.");
    }

    /**
     * Mirror the account name/email onto the linked employee record
     * (the employee profile is what the whole interface displays).
     */
    private function syncEmployeeProfile(User $user, ?string $name, ?string $email): void
    {
        $employee = $user->employee;

        if (! $employee) {
            return;
        }

        $changes = [];

        if ($name && trim($name) !== '') {
            $parts = preg_split('/\s+/', trim($name), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';

            if ($first !== $employee->first_name || $last !== $employee->last_name) {
                $changes['first_name'] = $first;
                $changes['last_name'] = $last ?: $employee->last_name;
            }
        }

        if ($email && strcasecmp($email, (string) $employee->email) !== 0) {
            $taken = Employee::query()
                ->where('email', $email)
                ->where('id', '!=', $employee->id)
                ->exists();

            if (! $taken) {
                $changes['email'] = $email;
            }
        }

        if ($changes) {
            $employee->update($changes + ['updated_by' => auth()->id()]);
        }
    }

    private function generateTemporaryPassword(): string
    {
        // Human-readable, strong enough for a one-time credential.
        return 'Bk' . \Illuminate\Support\Str::random(6) . '!' . random_int(10, 99);
    }

    public function syncRolePermissions(Request $request, Role $role)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::SYSTEM_ROLES)) {
            abort(403);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:160'],
        ]);

        $ids = \App\Models\Permission::query()->whereIn('slug', $data['permissions'] ?? [])->pluck('id')->all();
        $old = $role->permissions->pluck('slug')->all();
        $role->permissions()->sync($ids);

        AuditLogger::log('role.permissions_changed', Role::class, $role->id,
            "Updated permissions for role {$role->name}", ['permissions' => $old], ['permissions' => $data['permissions'] ?? []]);

        return back()->with('success', 'Role permissions updated.');
    }
}
