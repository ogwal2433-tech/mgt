import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { formatDate, formatDateTime } from '@/types/app';
import { Head, Link } from '@inertiajs/react';

export default function EmployeeShow({ employee }: any) {
    return (
        <AppLayout
            title={employee.full_name}
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Employees', href: '/employees' },
                { label: employee.employee_code },
            ]}
        >
            <Head title={employee.full_name} />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader
                            title={<span className="flex items-center gap-3">{employee.full_name} <Badge color={statusColor(employee.employment_status)}>{employee.employment_status}</Badge></span>}
                            subtitle={`${employee.employee_code} · ${employee.job_title ?? '—'}`}
                        />
                        <CardBody>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Item label="Employee ID" value={employee.employee_code} />
                                <Item label="Email" value={employee.email} />
                                <Item label="Phone" value={employee.phone} />
                                <Item label="Department" value={employee.department?.name} />
                                <Item label="Position" value={employee.position?.title} />
                                <Item label="Supervisor" value={employee.supervisor?.full_name} />
                                <Item label="Employment type" value={employee.employment_type?.replaceAll('_', ' ')} />
                                <Item label="Hire date" value={formatDate(employee.hire_date)} />
                                <Item label="Exit date" value={formatDate(employee.exit_date)} />
                                <Item label="Emergency contact" value={employee.emergency_contact_name ? `${employee.emergency_contact_name} (${employee.emergency_contact_phone ?? ''})` : '—'} />
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Employment contracts" />
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Contract</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Start</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">End</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(employee.contracts ?? []).length === 0 && (
                                        <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-500">No contracts recorded.</td></tr>
                                    )}
                                    {(employee.contracts ?? []).map((c: any) => (
                                        <tr key={c.id}>
                                            <td className="px-4 py-2">
                                                <Link href={`/contracts/${c.id}`} className="font-semibold text-green-700 hover:underline">{c.contract_number}</Link>
                                            </td>
                                            <td className="px-4 py-2">{c.contract_type?.replaceAll('_', ' ')}</td>
                                            <td className="px-4 py-2">{formatDate(c.start_date)}</td>
                                            <td className="px-4 py-2">{formatDate(c.end_date)}</td>
                                            <td className="px-4 py-2"><Badge color={statusColor(c.status)}>{c.status}</Badge></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader title="User account" />
                        <CardBody>
                            {employee.user ? (
                                <div className="space-y-3">
                                    <Item label="Username" value={employee.user.username} />
                                    <Item label="Email" value={employee.user.email} />
                                    <Item label="Status" value={<Badge color={employee.user.is_active ? 'green' : 'red'}>{employee.user.is_active ? 'Active' : 'Deactivated'}</Badge>} />
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">No system account is linked to this employee yet.</p>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Item({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase text-gray-500">{label}</p>
            <p className="mt-0.5 text-sm text-gray-900">{value ?? '—'}</p>
        </div>
    );
}
