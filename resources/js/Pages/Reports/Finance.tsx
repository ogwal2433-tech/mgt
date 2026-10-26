import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import Badge from '@/Components/ui/Badge';
import { formatMoney, formatDate } from '@/types/app';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function FinanceReports({ filters, summary, cashbook, aging, currency }: any) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [direction, setDirection] = useState(filters.direction ?? '');

    const apply = () => router.get('/reports/finance', { from, to, direction }, { preserveState: true });

    return (
        <AppLayout
            title="Finance Reports"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Finance Reports' }]}
            actions={<Button variant="secondary" onClick={() => window.print()}>Print</Button>}
        >
            <Head title="Finance Reports" />

            <div className="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Field label="From">
                        <TextInput type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </Field>
                    <Field label="To">
                        <TextInput type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </Field>
                    <Field label="Cashbook direction">
                        <SelectInput value={direction} onChange={(e) => { setDirection(e.target.value); router.get('/reports/finance', { from, to, direction: e.target.value }, { preserveState: true }); }}>
                            <option value="">All entries</option>
                            <option value="in">Money in only</option>
                            <option value="out">Money out only</option>
                        </SelectInput>
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button variant="secondary" onClick={apply}>Apply</Button>
                    </div>
                </div>
            </div>

            {/* Summary */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Invoiced" value={formatMoney(summary.invoiced, currency)} hint={`${summary.invoice_count} invoices`} />
                <Stat label="Collected from clients" value={formatMoney(summary.collected, currency)} accent="text-brand-700" />
                <Stat label="Receivables outstanding" value={formatMoney(summary.outstanding, currency)} accent="text-amber-600" hint={`${summary.overdue_count} overdue`} />
                <Stat label="Net cash flow" value={formatMoney(summary.net_cash_flow, currency)} accent={String(summary.net_cash_flow).startsWith('-') ? 'text-red-600' : 'text-brand-700'} />
            </div>

            {/* Every tracked movement */}
            <Card className="mt-6">
                <CardHeader title="Tracked cash movements" subtitle="Every movement in the system, by category" />
                <CardBody>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(summary.categories ?? {}).map(([label, amount]) => (
                            <div key={label} className="rounded-lg border border-gray-100 p-3">
                                <p className="text-xs text-ink-500">{label}</p>
                                <p className="mt-1 text-lg font-bold text-ink-900 tabular">{formatMoney(amount as string, currency)}</p>
                            </div>
                        ))}
                    </div>
                    {(cashbook?.category_totals && Object.keys(cashbook.category_totals).length > 0) && (
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                                <thead>
                                    <tr>
                                        {['Category', 'Entries', 'Money in', 'Money out'].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {Object.entries(cashbook.category_totals).map(([category, totals]: [string, any]) => (
                                        <tr key={category}>
                                            <td className="px-4 py-2.5 font-medium text-ink-900 capitalize">{category.replaceAll('_', ' ')}</td>
                                            <td className="px-4 py-2.5 tabular">{totals.count}</td>
                                            <td className="px-4 py-2.5 tabular text-brand-700">{formatMoney(totals.money_in, currency)}</td>
                                            <td className="px-4 py-2.5 tabular text-red-600">{formatMoney(totals.money_out, currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>

            {/* Aging */}
            <Card className="mt-6">
                <CardHeader
                    title="Receivables aging"
                    subtitle="Outstanding client balances by age — collect the oldest first"
                />
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                        <thead>
                            <tr>
                                <th className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">Client / invoice</th>
                                <th className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">Due date</th>
                                <th className="px-4 py-2.5 text-right text-2xs font-semibold uppercase tracking-wider text-ink-500">Days overdue</th>
                                <th className="px-4 py-2.5 text-right text-2xs font-semibold uppercase tracking-wider text-ink-500">Balance</th>
                                <th className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">Bucket</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(aging?.rows ?? []).length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-10 text-center text-sm text-ink-500">No outstanding receivables.</td></tr>
                            )}
                            {(aging?.rows ?? []).map((row: any) => (
                                <tr key={row.invoice_id} className="hover:bg-brand-50/40">
                                    <td className="px-4 py-2.5">
                                        <p className="font-semibold text-ink-900">{row.client_name}</p>
                                        <p className="text-xs text-ink-500">{row.invoice_number}</p>
                                    </td>
                                    <td className="px-4 py-2.5">{formatDate(row.due_date)}</td>
                                    <td className="px-4 py-2.5 text-right tabular">
                                        {row.days_overdue > 0 ? <span className="font-semibold text-red-600">{row.days_overdue}</span> : <span className="text-ink-500">—</span>}
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-semibold tabular">{formatMoney(row.amount_due, row.currency)}</td>
                                    <td className="px-4 py-2.5">
                                        <Badge color={row.bucket === 'current' ? 'green' : row.bucket === '90_plus' ? 'red' : 'amber'}>
                                            {aging.buckets[row.bucket]}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-ink-50 font-semibold">
                                <td className="px-4 py-2.5" colSpan={3}>Total outstanding</td>
                                <td className="px-4 py-2.5 text-right tabular">{formatMoney(aging.grand_total, currency)}</td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <CardBody>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        {Object.entries(aging.buckets ?? {}).map(([key, label]) => (
                            <div key={key} className="rounded-lg border border-gray-100 p-3">
                                <p className="text-xs text-ink-500">{label as string}</p>
                                <p className="mt-1 text-lg font-bold text-ink-900 tabular">{formatMoney(aging.totals[key], currency)}</p>
                            </div>
                        ))}
                    </div>
                </CardBody>
            </Card>

            {/* Cashbook */}
            <Card className="mt-6">
                <CardHeader
                    title="Cashbook"
                    subtitle={`Cash movements ${formatDate(filters.from)} – ${formatDate(filters.to)} with running balance`}
                />
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                        <thead>
                            <tr>
                                {['Date', 'Reference', 'Description', 'Method', 'In', 'Out', 'Balance'].map((h) => (
                                    <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            <tr className="bg-ink-50">
                                <td className="px-4 py-2.5 font-semibold" colSpan={6}>Opening balance</td>
                                <td className="px-4 py-2.5 font-semibold tabular">{formatMoney(cashbook.opening_balance, currency)}</td>
                            </tr>
                            {(cashbook?.entries ?? []).length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-10 text-center text-sm text-ink-500">No cash movements in this period.</td></tr>
                            )}
                            {(cashbook?.entries ?? []).map((entry: any, i: number) => (
                                <tr key={i} className="hover:bg-brand-50/40">
                                    <td className="px-4 py-2.5">{formatDate(entry.date)}</td>
                                    <td className="px-4 py-2.5 font-mono text-xs">{entry.reference}</td>
                                    <td className="px-4 py-2.5">{entry.description}</td>
                                    <td className="px-4 py-2.5">{entry.method?.replaceAll('_', ' ') ?? '—'}</td>
                                    <td className="px-4 py-2.5 tabular text-brand-700">
                                        {entry.money_in !== '0.00' ? formatMoney(entry.money_in, currency) : '—'}
                                    </td>
                                    <td className="px-4 py-2.5 tabular text-red-600">
                                        {entry.money_out !== '0.00' ? formatMoney(entry.money_out, currency) : '—'}
                                    </td>
                                    <td className="px-4 py-2.5 tabular font-medium">{formatMoney(entry.balance, currency)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-ink-50 font-semibold">
                                <td className="px-4 py-2.5" colSpan={4}>Totals</td>
                                <td className="px-4 py-2.5 tabular text-brand-700">{formatMoney(cashbook.total_in, currency)}</td>
                                <td className="px-4 py-2.5 tabular text-red-600">{formatMoney(cashbook.total_out, currency)}</td>
                                <td className="px-4 py-2.5 tabular">{formatMoney(cashbook.closing_balance, currency)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </AppLayout>
    );
}

function Stat({ label, value, hint, accent = 'text-ink-900' }: { label: string; value: string; hint?: string; accent?: string }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="eyebrow">{label}</p>
            <p className={`mt-2 text-2xl font-extrabold tracking-tight tabular ${accent}`}>{value}</p>
            {hint && <p className="mt-1 text-xs text-ink-500">{hint}</p>}
        </div>
    );
}
