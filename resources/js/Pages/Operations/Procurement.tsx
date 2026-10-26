import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import { formatMoney, formatDate } from '@/types/app';
import { Head, router } from '@inertiajs/react';

export default function OperationsProcurement({ suppliers, orders, stats }: any) {
    return (
        <AppLayout title="Procurement & Suppliers" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Procurement & Suppliers' }]}>
            <Head title="Procurement & Suppliers" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Active suppliers" value={stats.suppliers} />
                <StatCard label="Open purchase orders" value={stats.open_orders} accent="blue" />
                <StatCard label="Awaiting goods receipt" value={stats.pending_receipt} accent="amber" />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Suppliers" />
                    <ul className="divide-y divide-gray-100">
                        {(suppliers?.data ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No suppliers registered.</li>}
                        {(suppliers?.data ?? []).map((s: any) => (
                            <li key={s.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{s.name}</p>
                                    <p className="text-xs text-gray-500">{s.contact_person} · {s.email ?? '—'}</p>
                                </div>
                                <Badge color={statusColor(s.status)}>{s.status}</Badge>
                            </li>
                        ))}
                    </ul>
                    <Pagination meta={suppliers} onGoTo={(page) => router.get('/procurement', { page }, { preserveState: true })} />
                </Card>

                <Card>
                    <CardHeader title="Recent purchase orders" />
                    <ul className="divide-y divide-gray-100">
                        {(orders ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No purchase orders.</li>}
                        {(orders ?? []).map((o: any) => (
                            <li key={o.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{o.po_number}</p>
                                    <p className="text-xs text-gray-500">{o.supplier?.name} · {formatDate(o.order_date)}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-medium text-gray-900">{formatMoney(o.total_amount, o.currency)}</p>
                                    <Badge color={statusColor(o.status)}>{o.status}</Badge>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}
