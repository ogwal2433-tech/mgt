import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, PasswordInput, SelectInput, TextInput } from '@/Components/ui/Form';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toastSuccess, confirmAction } from '@/lib/notify';
import { formatDateTime } from '@/types/app';

export default function UsersIndex({ users, roles, employees, filters }: any) {
    const { flash } = usePage().props as any;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [showCreate, setShowCreate] = useState(false);
    const [editTarget, setEditTarget] = useState<any>(null);
    const [inviteResult, setInviteResult] = useState<any>(flash?.invite ?? null);
    const [inviting, setInviting] = useState(false);
    const [suspending, setSuspending] = useState(false);
    const [renewing, setRenewing] = useState(false);
    const [resettingLink, setResettingLink] = useState(false);
    const createForm = useForm({ username: '', name: '', email: '', password: '', password_confirmation: '', employee_id: '', roles: [] as number[], must_change_password: true, send_invitation: true });
    const editForm = useForm({ name: '', email: '', roles: [] as number[], is_active: true, reset_password: '' });

    useEffect(() => {
        if (flash?.invite) setInviteResult(flash.invite);
    }, [flash?.invite]);

    const columns: Column<any>[] = [
        { key: 'username', header: 'Username', render: (row) => <span className="font-semibold text-gray-900">{row.username}</span> },
        { key: 'name', header: 'Name' },
        { key: 'email', header: 'Email' },
        {
            key: 'employee',
            header: 'Employee',
            render: (row) => (row.employee ? `${row.employee.employee_code} — ${row.employee.full_name}` : '—'),
        },
        {
            key: 'roles',
            header: 'Roles',
            render: (row) => (
                <div className="flex flex-wrap gap-1">
                    {(row.roles ?? []).map((r: any) => <Badge key={r.id} color="blue">{r.name}</Badge>)}
                </div>
            ),
        },
        {
            key: 'is_active',
            header: 'Status',
            render: (row) => <Badge color={row.is_active ? 'green' : 'red'}>{row.is_active ? 'Active' : 'Deactivated'}</Badge>,
        },
        {
            key: 'last_login_at',
            header: 'Login Status',
            render: (row) => (
                row.last_login_at ? (
                    <span className="text-xs">
                        <Badge color="green">Signed in</Badge>
                        <span className="ms-1 text-ink-500">{formatDateTime(row.last_login_at)}</span>
                    </span>
                ) : (
                    <Badge color="gray">Never signed in</Badge>
                )
            ),
        },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <Button size="sm" variant="secondary" onClick={() => {
                    setEditTarget(row);
                    editForm.setData({ name: row.name, email: row.email, roles: (row.roles ?? []).map((r: any) => r.id), is_active: row.is_active, reset_password: '' });
                }}>
                    Manage
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Users & Permissions"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Users & Permissions' }]}
            actions={
                <div className="flex gap-2">
                    <Link href="/users/permissions/matrix"><Button variant="secondary">Role permission matrix</Button></Link>
                    <Button onClick={() => setShowCreate(true)}>Create account</Button>
                </div>
            }
        >
            <Head title="Users & Permissions" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Username, name, email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/users', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/users', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={users?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No user accounts found." />
                <Pagination meta={users} onGoTo={(page) => router.get('/users', { search, page }, { preserveState: true })} />
            </div>

            <Modal
                open={showCreate}
                onClose={() => setShowCreate(false)}
                title="Create user account"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
                        <Button loading={createForm.processing} onClick={() => createForm.post('/users', { onSuccess: () => setShowCreate(false) })}>
                            {createForm.data.send_invitation ? 'Create & send invitation' : 'Create account'}
                        </Button>
                    </>
                }
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {Object.keys(createForm.errors).length > 0 && (
                        <div className="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            <p className="font-semibold">Please fix the following:</p>
                            <ul className="mt-1 list-inside list-disc space-y-0.5">
                                {Object.entries(createForm.errors).map(([key, message]) => (
                                    <li key={key}>{message as string}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <Field label="Username" required error={createForm.errors.username}>
                        <TextInput value={createForm.data.username} onChange={(e) => createForm.setData('username', e.target.value)} />
                    </Field>
                    <Field label="Full name" required>
                        <TextInput value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Email" required error={createForm.errors.email}>
                        <TextInput type="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} />
                    </Field>
                    <Field label="Linked employee">
                        <SelectInput value={createForm.data.employee_id} onChange={(e) => createForm.setData('employee_id', e.target.value)}>
                            <option value="">None</option>
                            {(employees ?? []).map((emp: any) => <option key={emp.id} value={emp.id}>{emp.employee_code} — {emp.first_name} {emp.last_name}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Password" required={!createForm.data.send_invitation} error={createForm.errors.password}>
                        <PasswordInput
                            value={createForm.data.password}
                            disabled={createForm.data.send_invitation}
                            placeholder={createForm.data.send_invitation ? 'Temporary password is generated automatically' : ''}
                            onChange={(e) => createForm.setData('password', e.target.value)}
                        />
                    </Field>
                    <Field label="Confirm password" required={!createForm.data.send_invitation}>
                        <PasswordInput
                            value={createForm.data.password_confirmation}
                            disabled={createForm.data.send_invitation}
                            onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
                        />
                    </Field>
                    <Field label="Roles" required className="sm:col-span-2">
                        <div className="space-y-2 rounded-md border border-gray-200 p-3">
                            {(roles ?? []).map((role: any) => (
                                <label key={role.id} className="flex items-center gap-2 text-sm text-gray-800">
                                    <input
                                        type="checkbox"
                                        checked={createForm.data.roles.includes(role.id)}
                                        onChange={(e) => {
                                            const rolesSelected = e.target.checked
                                                ? [...createForm.data.roles, role.id]
                                                : createForm.data.roles.filter((id) => id !== role.id);
                                            createForm.setData('roles', rolesSelected);
                                        }}
                                        className="h-4 w-4 rounded border-gray-300 text-green-600"
                                    />
                                    <span className="font-medium">{role.name}</span>
                                    <span className="text-xs text-gray-500">— {role.description ?? role.slug}</span>
                                </label>
                            ))}
                        </div>
                    </Field>

                    <div className="sm:col-span-2 rounded-lg border border-brand-200 bg-brand-50/60 p-4">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                checked={createForm.data.send_invitation}
                                onChange={(e) => createForm.setData('send_invitation', e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded border-gray-300 text-green-600"
                            />
                            <span>
                                <span className="block text-sm font-semibold text-brand-900">
                                    Invite this employee by email
                                </span>
                                <span className="mt-1 block text-xs leading-relaxed text-brand-800">
                                    The system emails a personalized invitation with a temporary password. The employee must
                                    set a new password when they first sign in.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
                <p className="mt-3 text-xs text-gray-500">
                    System administration accounts receive configuration permissions only. Financial and HR authority must be granted explicitly.
                </p>
            </Modal>

            <Modal
                open={!!editTarget}
                onClose={() => setEditTarget(null)}
                title={`Manage ${editTarget?.username ?? ''}`}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setEditTarget(null)}>Cancel</Button>
                        <Button
                            variant="ghost"
                            loading={inviting}
                            onClick={() => {
                                if (!editTarget) return;
                                setInviting(true);
                                router.post(`/users/${editTarget.id}/invite`, {}, {
                                    onFinish: () => setInviting(false),
                                });
                            }}
                        >
                            Invite via email
                        </Button>
                        <Button loading={editForm.processing} onClick={() => editForm.put(`/users/${editTarget?.id}`, { onSuccess: () => setEditTarget(null) })}>Save changes</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {Object.keys(editForm.errors).length > 0 && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            <p className="font-semibold">Please fix the following:</p>
                            <ul className="mt-1 list-inside list-disc space-y-0.5">
                                {Object.entries(editForm.errors).map(([key, message]) => (
                                    <li key={key}>{message as string}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Account status: suspension / renewal */}
                    <div className="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="eyebrow">Account status</p>
                            <div className="mt-1 flex items-center gap-2">
                                <Badge color={editTarget?.is_active ? 'green' : 'red'}>
                                    {editTarget?.is_active ? 'Active' : 'Suspended'}
                                </Badge>
                                {editTarget?.deactivated_at && (
                                    <span className="text-xs text-ink-500">since {new Date(editTarget.deactivated_at).toLocaleDateString('en-GB')}</span>
                                )}
                            </div>
                        </div>
                        {editTarget?.is_active ? (
                            <Button
                                variant="secondary"
                                loading={suspending}
                                onClick={async () => {
                                    const ok = await confirmAction(
                                        'Suspend this account?',
                                        `${editTarget.username} will no longer be able to sign in. All records are preserved.`,
                                        'Suspend account',
                                    );
                                    if (!ok) return;
                                    setSuspending(true);
                                    router.post(`/users/${editTarget.id}/suspend`, {}, { onFinish: () => setSuspending(false) });
                                }}
                                className="border-red-200 text-red-700 hover:bg-red-50"
                            >
                                Suspend account
                            </Button>
                        ) : (
                            <Button
                                variant="primary"
                                loading={renewing}
                                onClick={async () => {
                                    const ok = await confirmAction(
                                        'Renew this account?',
                                        `${editTarget.username} will be able to sign in again.`,
                                        'Renew access',
                                    );
                                    if (!ok) return;
                                    setRenewing(true);
                                    router.post(`/users/${editTarget.id}/renew`, {}, { onFinish: () => setRenewing(false) });
                                }}
                            >
                                Renew access
                            </Button>
                        )}
                    </div>

                    <Field label="Full name" error={editForm.errors.name}>
                        <TextInput value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Email" error={editForm.errors.email}>
                        <TextInput type="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} />
                    </Field>
                    <Field label="Roles" error={editForm.errors.roles}>
                        <div className="space-y-2 rounded-md border border-gray-200 p-3">
                            {(roles ?? []).map((role: any) => (
                                <label key={role.id} className="flex items-center gap-2 text-sm text-gray-800">
                                    <input
                                        type="checkbox"
                                        checked={editForm.data.roles.includes(role.id)}
                                        onChange={(e) => {
                                            const rolesSelected = e.target.checked
                                                ? [...editForm.data.roles, role.id]
                                                : editForm.data.roles.filter((id) => id !== role.id);
                                            editForm.setData('roles', rolesSelected);
                                        }}
                                        className="h-4 w-4 rounded border-gray-300 text-green-600"
                                    />
                                    <span className="font-medium">{role.name}</span>
                                </label>
                            ))}
                        </div>
                    </Field>
                    <label className="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" checked={editForm.data.is_active} onChange={(e) => editForm.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-green-600" />
                        Account is active (deactivating preserves all historical records)
                    </label>
                    <Field
                        label="Reset password (optional - user must change it at next login)"
                        error={editForm.errors.reset_password}
                        hint="Minimum 8 characters. Leave blank to keep the current password."
                    >
                        <PasswordInput value={editForm.data.reset_password} onChange={(e) => editForm.setData('reset_password', e.target.value)} />
                    </Field>
                    <div className="flex flex-col gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold text-ink-800">Email password reset link</p>
                            <p className="text-xs text-ink-500">
                                Sends a secure, one-time link to {editTarget?.email ?? 'the user'} so they can set a new password themselves.
                            </p>
                        </div>
                        <Button
                            variant="secondary"
                            size="sm"
                            loading={resettingLink}
                            onClick={() => {
                                if (!editTarget) return;
                                setResettingLink(true);
                                router.post(`/users/${editTarget.id}/reset-link`, {}, { onFinish: () => setResettingLink(false) });
                            }}
                        >
                            Send reset link
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Invitation sent — temporary password */}
            <Modal
                open={!!inviteResult}
                onClose={() => setInviteResult(null)}
                title="Invitation sent"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setInviteResult(null)}>Close</Button>
                        <Button
                            onClick={() => {
                                navigator.clipboard?.writeText(inviteResult?.temporary_password ?? '');
                                toastSuccess('Copied!');
                            }}
                        >
                            Copy password
                        </Button>
                    </>
                }
            >
                {inviteResult && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-brand-200 bg-brand-50 p-4">
                            <p className="text-sm font-semibold text-brand-900">
                                Invitation emailed to {inviteResult.name} at <span className="font-bold">{inviteResult.email}</span>
                            </p>
                            <p className="mt-1 text-xs leading-relaxed text-brand-800">
                                Delivery status: <strong>{inviteResult.delivery_status}</strong>. The user must set a new
                                password when they first sign in — the temporary password works only until then.
                            </p>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <p className="eyebrow">Username</p>
                                    <p className="mt-1 font-mono text-sm font-semibold text-ink-900">{inviteResult.username}</p>
                                </div>
                                <div>
                                    <p className="eyebrow">Temporary password</p>
                                    <p className="mt-1 inline-block rounded-md bg-ink-900 px-3 py-1.5 font-mono text-sm font-semibold text-brand-400">
                                        {inviteResult.temporary_password}
                                    </p>
                                </div>
                            </div>
                            <p className="mt-3 text-xs text-ink-500">
                                Sign-in link: <span className="font-medium text-ink-700">{inviteResult.login_url}</span>
                            </p>
                        </div>

                        <p className="text-xs leading-relaxed text-ink-500">
                            Share these details through a secure channel if the email does not arrive. If email delivery
                            failed, fix the mail settings and use <strong>Invite via email</strong> again — a new temporary
                            password will be issued.
                        </p>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
