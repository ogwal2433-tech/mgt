import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import { Head, router, useForm } from '@inertiajs/react';

export default function PermissionsMatrix({ roles, catalogue }: any) {
    return (
        <AppLayout
            title="Role Permission Matrix"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Users', href: '/users' }, { label: 'Permissions' }]}
        >
            <Head title="Role Permission Matrix" />

            <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                Permissions are enforced on the server for every protected operation. Hiding a button in the interface is never a security
                control. The system administrator role holds configuration permissions only unless financial or HR permissions are explicitly
                granted — and every grant is audited.
            </div>

            <div className="space-y-6">
                {(roles ?? []).map((role: any) => (
                    <RoleEditor key={role.id} role={role} catalogue={catalogue} />
                ))}
            </div>
        </AppLayout>
    );
}

function RoleEditor({ role, catalogue }: any) {
    const assigned: string[] = (role.permissions ?? []).map((p: any) => p.slug);
    const form = useForm<{ permissions: string[] }>({ permissions: [...assigned] });
    const groups: Record<string, [string, string, string][]> = {};

    Object.entries(catalogue ?? {}).forEach(([slug, meta]: [string, any]) => {
        const group = slug.split('.')[0];
        if (!groups[group]) groups[group] = [];
        groups[group].push([slug, meta[0], meta[1]]);
    });

    return (
        <Card>
            <CardHeader
                title={
                    <span className="flex items-center gap-3">
                        {role.name}
                        <Badge color="blue">{role.slug}</Badge>
                        {role.is_system && <Badge color="gray">system role</Badge>}
                    </span>
                }
                subtitle={role.description}
                actions={
                    <Button
                        size="sm"
                        loading={form.processing}
                        onClick={() => form.put(`/roles/${role.id}/permissions`)}
                    >
                        Save permissions
                    </Button>
                }
            />
            <div className="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
                {Object.entries(groups).map(([group, entries]) => (
                    <div key={group} className="rounded-md border border-gray-100 p-3">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{group}</p>
                        <div className="space-y-2">
                            {entries.map(([slug, label, description]) => (
                                <label key={slug} className="flex items-start gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.permissions.includes(slug)}
                                        onChange={(e) => {
                                            const permissions = e.target.checked
                                                ? [...form.data.permissions, slug]
                                                : form.data.permissions.filter((p) => p !== slug);
                                            form.setData('permissions', permissions);
                                        }}
                                        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-green-600"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-gray-800">{label}</span>
                                        <span className="block text-xs text-gray-500">{description}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
}
