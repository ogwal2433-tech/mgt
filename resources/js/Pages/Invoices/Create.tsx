import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import { formatMoney } from '@/types/app';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function InvoiceCreate({ parties, projects }: any) {
    const { data, setData, post, processing, errors } = useForm({
        client_name: '',
        client_contact: '',
        client_email: '',
        client_address: '',
        external_party_id: '',
        project_id: '',
        issue_date: new Date().toISOString().slice(0, 10),
        due_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
        currency: 'UGX',
        tax_rate: '0',
        terms: 'Payment due within 30 days of issue.',
        notes: '',
        items: [{ description: '', quantity: '1', unit: '', unit_price: '0' }],
    });

    const recalc = (items: typeof data.items) =>
        items.reduce((sum, item) => sum + (parseFloat(item.quantity || '0') || 0) * (parseFloat(item.unit_price || '0') || 0), 0);

    const updateItem = (index: number, patch: Partial<(typeof data.items)[number]>) => {
        setData('items', data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    };

    const subtotal = recalc(data.items);
    const tax = subtotal * (parseFloat(data.tax_rate || '0') / 100);
    const total = subtotal + tax;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/invoices');
    };

    return (
        <AppLayout
            title="New Client Invoice"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Invoices', href: '/invoices' }, { label: 'New' }]}
        >
            <Head title="New Invoice" />

            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader title="Client & project" subtitle="Invoice any client for a project undertaken" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Client name" required error={errors.client_name}>
                            <TextInput value={data.client_name} onChange={(e) => setData('client_name', e.target.value)} />
                        </Field>
                        <Field label="Contact person">
                            <TextInput value={data.client_contact} onChange={(e) => setData('client_contact', e.target.value)} />
                        </Field>
                        <Field label="Client email" error={errors.client_email}>
                            <TextInput type="email" value={data.client_email} onChange={(e) => setData('client_email', e.target.value)} />
                        </Field>
                        <Field label="Client address">
                            <TextInput value={data.client_address} onChange={(e) => setData('client_address', e.target.value)} />
                        </Field>
                        <Field label="Project">
                            <SelectInput value={data.project_id} onChange={(e) => setData('project_id', e.target.value)}>
                                <option value="">None</option>
                                {(projects ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.project_code} — {p.name}</option>)}
                            </SelectInput>
                        </Field>
                        <Field label="Existing party (optional)">
                            <SelectInput
                                value={data.external_party_id}
                                onChange={(e) => {
                                    const party = (parties ?? []).find((p: any) => String(p.id) === e.target.value);
                                    setData('external_party_id', e.target.value);
                                    if (party) {
                                        setData('client_name', party.name);
                                        setData('client_contact', party.contact_person ?? '');
                                        setData('client_email', party.email ?? '');
                                        setData('client_address', party.address ?? '');
                                    }
                                }}
                            >
                                <option value="">New client</option>
                                {(parties ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </SelectInput>
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Invoice details" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Issue date" required>
                            <TextInput type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} />
                        </Field>
                        <Field label="Due date" required error={errors.due_date}>
                            <TextInput type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                        </Field>
                        <Field label="Currency" required>
                            <TextInput value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                        </Field>
                        <Field label="Tax rate (%)" hint="Applied on the subtotal">
                            <TextInput type="number" step="0.01" min="0" value={data.tax_rate} onChange={(e) => setData('tax_rate', e.target.value)} />
                        </Field>
                        <Field label="Payment terms" className="sm:col-span-2">
                            <TextArea rows={2} value={data.terms} onChange={(e) => setData('terms', e.target.value)} />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="Items"
                        actions={
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => setData('items', [...data.items, { description: '', quantity: '1', unit: '', unit_price: '0' }])}
                            >
                                Add item
                            </Button>
                        }
                    />
                    <CardBody className="space-y-3">
                        {data.items.map((item, index) => (
                            <div key={index} className="grid grid-cols-1 items-end gap-3 rounded-lg border border-gray-100 p-3 sm:grid-cols-12">
                                <Field label="Description" className="sm:col-span-5" required>
                                    <TextInput value={item.description} onChange={(e) => updateItem(index, { description: e.target.value })} />
                                </Field>
                                <Field label="Qty" className="sm:col-span-2" required>
                                    <TextInput type="number" step="0.01" min="0.01" value={item.quantity} onChange={(e) => updateItem(index, { quantity: e.target.value })} />
                                </Field>
                                <Field label="Unit" className="sm:col-span-2">
                                    <TextInput value={item.unit} onChange={(e) => updateItem(index, { unit: e.target.value })} />
                                </Field>
                                <Field label="Unit price" className="sm:col-span-2" required>
                                    <TextInput type="number" step="0.01" min="0" value={item.unit_price} onChange={(e) => updateItem(index, { unit_price: e.target.value })} />
                                </Field>
                                <div className="sm:col-span-1">
                                    <Button
                                        type="button"
                                        variant="danger"
                                        size="sm"
                                        onClick={() => setData('items', data.items.filter((_, i) => i !== index))}
                                    >
                                        ×
                                    </Button>
                                </div>
                            </div>
                        ))}
                        <div className="space-y-1 rounded-lg bg-brand-50 px-4 py-3 text-right text-sm">
                            <p className="text-ink-600">Subtotal: <span className="font-semibold text-ink-900 tabular">{formatMoney(subtotal.toFixed(2), data.currency)}</span></p>
                            <p className="text-ink-600">Tax: <span className="font-semibold text-ink-900 tabular">{formatMoney(tax.toFixed(2), data.currency)}</span></p>
                            <p className="text-base font-extrabold text-ink-900">Total: {formatMoney(total.toFixed(2), data.currency)}</p>
                        </div>
                    </CardBody>
                </Card>

                <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs text-ink-500">Invoices can be sent to clients as printable PDFs and tracked to payment.</p>
                    <div className="flex gap-2">
                        <Link href="/invoices"><Button type="button" variant="secondary">Cancel</Button></Link>
                        <Button type="submit" loading={processing}>Create invoice</Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
