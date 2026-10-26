import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import { formatMoney } from '@/types/app';
import { Head, router } from '@inertiajs/react';

export default function OperationsInventory({ items, lowStock, assets, stats }: any) {
    return (
        <AppLayout title="Inventory & Assets" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Inventory & Assets' }]}>
            <Head title="Inventory & Assets" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Inventory items" value={stats.items} />
                <StatCard label="Below minimum stock" value={stats.low_stock} accent="red" />
                <StatCard label="Registered assets" value={stats.assets} accent="blue" />
                <StatCard label="Assets assigned" value={stats.assigned} accent="green" />
            </div>

            {(lowStock ?? []).length > 0 && (
                <div className="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                    <p className="text-sm font-semibold text-red-900">Stock items at or below minimum level</p>
                    <ul className="mt-2 space-y-1 text-sm text-red-800">
                        {lowStock.map((i: any) => (
                            <li key={i.id} className="flex justify-between">
                                <span>{i.item_code} — {i.name}</span>
                                <span>{i.quantity_on_hand} / min {i.min_stock_level}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Inventory catalogue" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Item code</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Name</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">On hand</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Unit cost</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(items?.data ?? []).length === 0 && (
                                    <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-500">No inventory items.</td></tr>
                                )}
                                {(items?.data ?? []).map((i: any) => (
                                    <tr key={i.id}>
                                        <td className="px-4 py-2 font-mono text-xs">{i.item_code}</td>
                                        <td className="px-4 py-2">{i.name}</td>
                                        <td className="px-4 py-2 text-right">{i.quantity_on_hand} {i.unit}</td>
                                        <td className="px-4 py-2 text-right">{formatMoney(i.unit_cost)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination meta={items} onGoTo={(page) => router.get('/inventory', { page }, { preserveState: true })} />
                </Card>

                <Card>
                    <CardHeader title="Asset register" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Tag</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Asset</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Custodian</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(assets ?? []).length === 0 && (
                                    <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-500">No assets registered.</td></tr>
                                )}
                                {(assets ?? []).map((a: any) => (
                                    <tr key={a.id}>
                                        <td className="px-4 py-2 font-mono text-xs">{a.asset_tag}</td>
                                        <td className="px-4 py-2">{a.name}</td>
                                        <td className="px-4 py-2">{a.assignee?.full_name ?? '—'}</td>
                                        <td className="px-4 py-2"><Badge color={statusColor(a.status)}>{a.status}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
