import AppLayout from '@/Layouts/AppLayout';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import { formatMoney, formatDate } from '@/types/app';
import { Head, router } from '@inertiajs/react';

export default function MyPayslips({ payslips }: any) {
    return (
        <AppLayout title="My Payslips" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'My Payslips' }]}>
            <Head title="My Payslips" />

            <div className="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                Every salary payment produces a payslip. It is emailed to you and always available here for download.
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                        <thead>
                            <tr>
                                {['Payslip', 'Period', 'Gross', 'Deductions', 'Net pay', 'Payment date', 'Email', 'Download'].map((h) => (
                                    <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(payslips?.data ?? []).length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-14 text-center">
                                    <p className="text-sm font-medium text-ink-700">No payslips yet</p>
                                    <p className="mt-1 text-xs text-ink-500">Your payslips appear here as soon as payroll is paid.</p>
                                </td></tr>
                            )}
                            {(payslips?.data ?? []).map((line: any) => (
                                <tr key={line.id} className="hover:bg-brand-50/40">
                                    <td className="px-4 py-2.5 font-semibold text-ink-900">{line.payslip_number}</td>
                                    <td className="px-4 py-2.5">{line.run?.period?.name}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.gross_amount)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.total_deductions)}</td>
                                    <td className="px-4 py-2.5 font-semibold tabular">{formatMoney(line.net_amount)}</td>
                                    <td className="px-4 py-2.5">{formatDate(line.payment?.payment_date)}</td>
                                    <td className="px-4 py-2.5"><Badge color={statusColor(line.payslip_delivery_status)}>{line.payslip_delivery_status}</Badge></td>
                                    <td className="px-4 py-2.5">
                                        <a href={`/my-payslips/${line.id}/pdf`} className="font-semibold text-brand-700 hover:underline">
                                            Download PDF
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination meta={payslips} onGoTo={(page) => router.get('/my-payslips', { page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
