import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import { formatMoney, formatDate, formatDateTime } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';

export default function ReceiptShow({ receipt }: any) {
    return (
        <AppLayout
            title={`Receipt ${receipt.receipt_number}`}
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Receipts', href: '/receipts' },
                { label: receipt.receipt_number },
            ]}
            actions={
                <div className="flex gap-2">
                    <a href={`/receipts/${receipt.id}/pdf`}>
                        <Button>Download PDF</Button>
                    </a>
                    <Button variant="secondary" onClick={() => router.post(`/receipts/${receipt.id}/retry-delivery`)}>
                        Retry email delivery
                    </Button>
                </div>
            }
        >
            <Head title={`Receipt ${receipt.receipt_number}`} />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader
                            title={<span className="flex items-center gap-3">Official payment receipt <Badge color={statusColor(receipt.delivery_status)}>{receipt.delivery_status}</Badge></span>}
                            subtitle={`Generated ${formatDateTime(receipt.generated_at)}`}
                        />
                        <CardBody>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Item label="Receipt number" value={receipt.receipt_number} />
                                <Item label="Payment number" value={receipt.payment?.payment_number} />
                                <Item label="Requisition number" value={<Link href={`/requisitions/${receipt.requisition?.id}`} className="text-green-700 hover:underline">{receipt.requisition?.requisition_number}</Link>} />
                                <Item label="Payment reference" value={receipt.payment_reference ?? '—'} />
                                <Item label="Requester" value={receipt.requester?.name} />
                                <Item label="Payee" value={receipt.payee_name} />
                                <Item label="Purpose" value={receipt.purpose} />
                                <Item label="Payment method" value={receipt.payment_method?.replaceAll('_', ' ')} />
                                <Item label="Payment date" value={formatDate(receipt.payment_date)} />
                                <Item label="Recorded by" value={receipt.generated_by?.name} />
                                <Item label="Amount paid" value={<span className="text-lg font-bold text-green-700">{formatMoney(receipt.amount, receipt.currency)}</span>} />
                                <Item label="Remaining approved balance" value={formatMoney(receipt.remaining_balance, receipt.currency)} />
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="mt-6">
                        <CardHeader title="Delivery" subtitle="In-app notification and email delivery status" />
                        <CardBody>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <Item label="Delivery status" value={<Badge color={statusColor(receipt.delivery_status)}>{receipt.delivery_status}</Badge>} />
                                <Item label="Delivered at" value={formatDateTime(receipt.delivered_at)} />
                                <Item label="Retry count" value={receipt.retry_count ?? 0} />
                            </div>
                            {receipt.delivery_error && (
                                <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                    <strong>Delivery error:</strong> {receipt.delivery_error}
                                </div>
                            )}
                            {receipt.document && (
                                <div className="mt-4">
                                    <a href={`/documents/${receipt.document.uuid}/download`} className="text-sm font-semibold text-green-700 hover:underline">
                                        Download stored receipt PDF
                                    </a>
                                </div>
                            )}
                        </CardBody>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader title="Printable receipt" />
                        <CardBody>
                            <a href={`/receipts/${receipt.id}/pdf`} className="block">
                                <div className="rounded-lg border-2 border-green-200 bg-green-50 p-6 text-center">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-green-700">Official document</p>
                                    <p className="mt-2 text-lg font-bold text-gray-900">{receipt.receipt_number}</p>
                                    <p className="mt-1 text-sm text-gray-600">{formatMoney(receipt.amount, receipt.currency)}</p>
                                    <p className="mt-3 text-xs text-gray-500">Click to open the PDF receipt</p>
                                </div>
                            </a>
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
