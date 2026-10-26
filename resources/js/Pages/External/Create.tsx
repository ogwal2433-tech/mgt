import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import { formatMoney } from '@/types/app';
import { Head, Link, useForm } from '@inertiajs/react';
import { toastError } from '@/lib/notify';
import { FormEventHandler } from 'react';

export default function ExternalCreate({ parties, projects }: any) {
    const { data, setData, post, processing, errors } = useForm({
        date: new Date().toISOString().slice(0, 10),
        external_party_id: '',
        external_party_name: '',
        external_contact: '',
        external_email: '',
        external_phone: '',
        requested_by_name: '',
        payee_name: '',
        department_text: '',
        mode_of_payment: 'bank_transfer',
        date_of_activity: '',
        activity_budget: '',
        project_id: '',
        client_reference: '',
        purpose: '',
        currency: 'UGX',
        total_amount: '0.00',
        required_delivery_date: '',
        terms: '',
        notes: '',
        items: [{ description: '', quantity: '1', unit: '', unit_price: '0' }],
        attachments: [] as File[],
    });

    const recalc = (items: typeof data.items) =>
        items.reduce((sum, item) => sum + (parseFloat(item.quantity || '0') || 0) * (parseFloat(item.unit_price || '0') || 0), 0).toFixed(2);

    const updateItem = (index: number, patch: Partial<(typeof data.items)[number]>) => {
        const items = data.items.map((item, i) => (i === index ? { ...item, ...patch } : item));
        setData('items', items);
        setData('total_amount', recalc(items));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Visible feedback before anything else — never a silent no-op.
        const blank = data.items.some((item) => item.description.trim() || (parseFloat(item.unit_price || '0') || 0) > 0
            ? !item.description.trim() || (parseFloat(item.unit_price || '0') || 0) <= 0
            : false);
        if (blank) {
            toastError('Each filled item line needs a description and a price above zero');
            return;
        }
        if ((parseFloat(data.total_amount || '0') || 0) <= 0) {
            toastError('The total estimated amount must be greater than zero');
            return;
        }
        if (!data.external_party_name.trim()) {
            toastError('Please enter the external organisation name');
            return;
        }

        const fd = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            if (key === 'items' || key === 'attachments') return;
            if (value) fd.append(key, String(value));
        });
        data.items.forEach((item, index) => {
            fd.append(`items[${index}][description]`, item.description);
            fd.append(`items[${index}][quantity]`, item.quantity);
            if (item.unit) fd.append(`items[${index}][unit]`, item.unit);
            fd.append(`items[${index}][unit_price]`, item.unit_price);
        });
        data.attachments.forEach((file) => fd.append('attachments[]', file));

        post('/external-requisitions', { data: fd, forceFormData: true } as any);
    };

    return (
        <AppLayout
            title="New External Requisition"
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'External Requisitions', href: '/external-requisitions' },
                { label: 'New' },
            ]}
        >
            <Head title="New External Requisition" />

            <form onSubmit={submit} className="space-y-6">
                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                        <p className="text-sm font-semibold text-red-800">Please fix the following before creating:</p>
                        <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                            {Object.entries(errors).map(([key, message]) => (
                                <li key={key}>{message as string}</li>
                            ))}
                        </ul>
                    </div>
                )}
                <Card>
                    <CardHeader title="External party" subtitle="Suppliers, contractors, clients or other external organisations" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Date" required>
                            <TextInput type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                        </Field>
                        <Field label="Existing party (optional)">
                            <SelectInput
                                value={data.external_party_id}
                                onChange={(e) => {
                                    const party = (parties ?? []).find((p: any) => String(p.id) === e.target.value);
                                    setData('external_party_id', e.target.value);
                                    if (party) {
                                        setData('external_party_name', party.name);
                                        setData('external_contact', party.contact_person ?? '');
                                        setData('external_email', party.email ?? '');
                                        setData('external_phone', party.phone ?? '');
                                    }
                                }}
                            >
                                <option value="">New / manual entry</option>
                                {(parties ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </SelectInput>
                        </Field>
                        <Field label="Organisation name" required error={errors.external_party_name}>
                            <TextInput value={data.external_party_name} onChange={(e) => setData('external_party_name', e.target.value)} />
                        </Field>
                        <Field label="Contact person">
                            <TextInput value={data.external_contact} onChange={(e) => setData('external_contact', e.target.value)} />
                        </Field>
                        <Field label="Email">
                            <TextInput type="email" value={data.external_email} onChange={(e) => setData('external_email', e.target.value)} />
                        </Field>
                        <Field label="Phone">
                            <TextInput value={data.external_phone} onChange={(e) => setData('external_phone', e.target.value)} />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Form details" subtitle="Printed on the Requisition / Accountability form" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Requested by">
                            <TextInput value={data.requested_by_name} onChange={(e) => setData('requested_by_name', e.target.value)} />
                        </Field>
                        <Field label="Payee">
                            <TextInput value={data.payee_name} onChange={(e) => setData('payee_name', e.target.value)} />
                        </Field>
                        <Field label="Dept / organisation">
                            <TextInput value={data.department_text} onChange={(e) => setData('department_text', e.target.value)} />
                        </Field>
                        <Field label="Mode of payment">
                            <SelectInput value={data.mode_of_payment} onChange={(e) => setData('mode_of_payment', e.target.value)}>
                                {[
                                    ['bank_transfer', 'Bank transfer'],
                                    ['mobile_money', 'Mobile money'],
                                    ['cash', 'Cash'],
                                    ['cheque', 'Cheque'],
                                    ['other', 'Other'],
                                ].map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </SelectInput>
                        </Field>
                        <Field label="Date of activity">
                            <TextInput type="date" value={data.date_of_activity} onChange={(e) => setData('date_of_activity', e.target.value)} />
                        </Field>
                        <Field label="Activity budget">
                            <TextInput type="number" step="0.01" min="0" value={data.activity_budget} onChange={(e) => setData('activity_budget', e.target.value)} />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Request details" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Project / client reference">
                            <SelectInput value={data.project_id} onChange={(e) => setData('project_id', e.target.value)}>
                                <option value="">None</option>
                                {(projects ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.project_code} — {p.name}</option>)}
                            </SelectInput>
                        </Field>
                        <Field label="Client reference">
                            <TextInput value={data.client_reference} onChange={(e) => setData('client_reference', e.target.value)} />
                        </Field>
                        <Field label="Purpose" required className="sm:col-span-2" error={errors.purpose}>
                            <TextArea rows={3} value={data.purpose} onChange={(e) => setData('purpose', e.target.value)} />
                        </Field>
                        <Field label="Currency" required>
                            <TextInput value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                        </Field>
                        <Field label="Required delivery date">
                            <TextInput type="date" value={data.required_delivery_date} onChange={(e) => setData('required_delivery_date', e.target.value)} />
                        </Field>
                        <Field label="Terms / notes" className="sm:col-span-2">
                            <TextArea rows={3} value={data.terms} onChange={(e) => setData('terms', e.target.value)} />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="Items (optional)"
                        subtitle="Add itemised lines if useful — or skip and enter the total estimated amount directly"
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
                        {data.items.length === 0 && (
                            <p className="rounded-md border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-ink-500">
                                No item lines — that is fine. Enter the total estimated amount below.
                            </p>
                        )}
                        {data.items.map((item, index) => (
                            <div key={index} className="grid grid-cols-1 items-end gap-3 rounded-md border border-gray-100 p-3 sm:grid-cols-12">
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
                                    <Button type="button" variant="danger" size="sm" onClick={() => {
                                        const items = data.items.filter((_, i) => i !== index);
                                        setData('items', items);
                                        setData('total_amount', recalc(items));
                                    }}>×</Button>
                                </div>
                            </div>
                        ))}
                        <div className="rounded-md bg-green-50 px-4 py-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div className="text-sm text-green-800">
                                    {data.items.length > 0 ? (
                                        <>Sum of item lines: <strong className="tabular">{formatMoney(recalc(data.items), data.currency)}</strong></>
                                    ) : (
                                        <>Enter the amount this form covers.</>
                                    )}
                                </div>
                                <Field label="Total estimated amount" required className="w-full sm:w-64">
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={data.total_amount}
                                        onChange={(e) => setData('total_amount', e.target.value)}
                                    />
                                </Field>
                            </div>
                        </div>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Attachments (optional)" subtitle="Quotations, technical specifications, supporting documents — not required" />
                    <CardBody>
                        <input
                            type="file"
                            multiple
                            onChange={(e) => setData('attachments', Array.from(e.target.files ?? []))}
                            className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700 hover:file:bg-green-100"
                        />
                    </CardBody>
                </Card>

                <div className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs text-gray-500">
                        Generating or printing the PDF form does not bypass internal approval requirements.
                    </p>
                    <div className="flex gap-2">
                        <Link href="/external-requisitions"><Button type="button" variant="secondary">Cancel</Button></Link>
                        <Button type="submit" loading={processing}>Create external requisition</Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
