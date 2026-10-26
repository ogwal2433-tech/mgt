import AppLayout from '@/Layouts/AppLayout';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import { formatMoney, formatDateTime } from '@/types/app';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function PayrollIndex({ runs, periods, components, can }: any) {
    const [showPeriod, setShowPeriod] = useState(false);
    const periodForm = useForm({ year: String(new Date().getFullYear()), month: String(new Date().getMonth() + 1), pay_date: '' });

    return (
        <AppLayout
            title="Payroll"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Payroll' }]}
            actions={can.run ? <Button onClick={() => setShowPeriod(true)}>New pay period</Button> : null}
        >
            <Head title="Payroll" />

            <div className="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                Payroll computes <strong>basic salary + stipends</strong>, less <strong>taxes, advance recoveries and statutory deductions</strong>.
                Runs must be approved before salary payments are recorded; every paid employee receives a payslip by email and in their account.
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <CardHeader title="Payroll runs" subtitle="One run per pay period" />
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                        <thead>
                            <tr>
                                {['Run', 'Period', 'Status', 'Basic', 'Gross', 'Deductions', 'Net pay', 'Paid', ''].map((h) => (
                                    <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(runs ?? []).length === 0 && (
                                <tr><td colSpan={9} className="px-4 py-12 text-center text-sm text-ink-500">
                                    No payroll runs yet. Create a pay period to begin.
                                </td></tr>
                            )}
                            {(runs ?? []).map((run: any) => (
                                <tr key={run.id} className="hover:bg-brand-50/40">
                                    <td className="px-4 py-2.5 font-semibold text-ink-900">{run.run_number}</td>
                                    <td className="px-4 py-2.5">{run.period}</td>
                                    <td className="px-4 py-2.5"><Badge color={statusColor(run.status)}>{run.status}</Badge></td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(run.total_basic)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(run.total_gross)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(run.total_deductions)}</td>
                                    <td className="px-4 py-2.5 font-semibold tabular">{formatMoney(run.total_net)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(run.total_paid)}</td>
                                    <td className="px-4 py-2.5">
                                        <Link href={`/payroll/runs/${run.id}`} className="font-semibold text-brand-700 hover:underline">Open</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Card className="mt-6">
                <CardHeader title="Salary components" subtitle="Configurable earnings and deductions used in every computation" />
                <CardBody>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {(components ?? []).map((c: any) => (
                            <div key={c.id} className="rounded-lg border border-gray-100 p-3">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-semibold text-ink-900">{c.name}</p>
                                    <Badge color={c.type === 'earning' ? 'green' : 'amber'}>{c.type}</Badge>
                                </div>
                                <p className="mt-1 text-xs text-ink-500">
                                    {c.calculation_type === 'fixed' ? formatMoney(c.default_amount) : `${c.default_amount}% of basic`}
                                    {c.is_statutory ? ' · statutory' : ''}{c.is_taxable ? '' : ' · non-taxable'}
                                </p>
                            </div>
                        ))}
                    </div>
                </CardBody>
            </Card>

            <Modal
                open={showPeriod}
                onClose={() => setShowPeriod(false)}
                title="Create pay period"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowPeriod(false)}>Cancel</Button>
                        <Button
                            loading={periodForm.processing}
                            onClick={() => periodForm.post('/payroll/periods', { onSuccess: () => setShowPeriod(false) })}
                        >
                            Create period
                        </Button>
                    </>
                }
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Year" required>
                        <SelectInput value={periodForm.data.year} onChange={(e) => periodForm.setData('year', e.target.value)}>
                            {[0, 1, 2].map((offset) => {
                                const y = new Date().getFullYear() + offset - 1;
                                return <option key={y} value={y}>{y}</option>;
                            })}
                        </SelectInput>
                    </Field>
                    <Field label="Month" required>
                        <SelectInput value={periodForm.data.month} onChange={(e) => periodForm.setData('month', e.target.value)}>
                            {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                <option key={m} value={m}>{new Date(2000, m - 1, 1).toLocaleString('en-GB', { month: 'long' })}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Pay date (optional)" className="sm:col-span-2">
                        <TextInput type="date" value={periodForm.data.pay_date} onChange={(e) => periodForm.setData('pay_date', e.target.value)} />
                    </Field>
                </div>
                <p className="mt-3 text-xs text-ink-500">
                    Existing periods ({(periods ?? []).map((p: any) => p.name).join(', ') || 'none'}) are reused automatically.
                </p>
            </Modal>
        </AppLayout>
    );
}
