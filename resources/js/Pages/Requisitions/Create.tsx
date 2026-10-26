import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import { formatMoney } from '@/types/app';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type LineType = 'goods' | 'service' | 'activity';
type ItemRow = {
    line_type: LineType;
    description: string;
    quantity: string;
    hours: string;
    unit: string;
    period: string;
    unit_cost: string;
};

const lineLabels: Record<LineType, { name: string; hint: string }> = {
    goods: { name: 'Goods', hint: 'Quantity x unit cost' },
    service: { name: 'Service', hint: 'Hours x rate (or quantity x rate)' },
    activity: { name: 'Activity', hint: 'Flat amount — dev work, milestones, support' },
};

const lineTotal = (item: ItemRow): number => {
    const rate = parseFloat(item.unit_cost || '0') || 0;
    if (item.line_type === 'service' && item.hours && parseFloat(item.hours) > 0) {
        return (parseFloat(item.hours) || 0) * rate;
    }
    return (parseFloat(item.quantity || '1') || 1) * rate;
};

const emptyItem = (): ItemRow => ({
    line_type: 'goods',
    description: '',
    quantity: '1',
    hours: '',
    unit: '',
    period: '',
    unit_cost: '0',
});

export default function RequisitionCreate({ categories, departments, projects, currencies }: any) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        category_id: '',
        project_id: '',
        purpose: '',
        justification: '',
        currency: 'UGX',
        requested_amount: '0.00',
        priority: 'normal',
        required_date: '',
        supplier_name: '',
        supplier_contact: '',
        budget_reference: '',
        is_advance: false,
        accountability_required: false,
        items: [emptyItem()] as ItemRow[],
        attachments: [] as File[],
    });

    const [submitNow, setSubmitNow] = useState(true);

    const recalcTotal = (items: ItemRow[]) =>
        items.reduce((sum, item) => sum + lineTotal(item), 0).toFixed(2);

    const updateItem = (index: number, patch: Partial<ItemRow>) => {
        const items = data.items.map((item, i) => (i === index ? { ...item, ...patch } : item));
        setData('items', items);
        setData('requested_amount', recalcTotal(items));
    };

    const setLineType = (index: number, line_type: LineType) => {
        updateItem(index, {
            line_type,
            quantity: line_type === 'activity' ? '1' : '1',
            hours: '',
        });
    };

    const addItem = () => {
        const items = [...data.items, emptyItem()];
        setData('items', items);
    };

    const removeItem = (index: number) => {
        const items = data.items.filter((_, i) => i !== index);
        setData('items', items);
        setData('requested_amount', items.length ? recalcTotal(items) : data.requested_amount);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('title', data.title);
        fd.append('category_id', data.category_id);
        if (data.project_id) fd.append('project_id', data.project_id);
        if (data.purpose) fd.append('purpose', data.purpose);
        if (data.justification) fd.append('justification', data.justification);
        fd.append('currency', data.currency);
        fd.append('requested_amount', data.requested_amount);
        fd.append('priority', data.priority);
        if (data.required_date) fd.append('required_date', data.required_date);
        if (data.supplier_name) fd.append('supplier_name', data.supplier_name);
        if (data.supplier_contact) fd.append('supplier_contact', data.supplier_contact);
        if (data.budget_reference) fd.append('budget_reference', data.budget_reference);
        fd.append('is_advance', data.is_advance ? '1' : '0');
        fd.append('accountability_required', data.accountability_required ? '1' : '0');
        data.items.forEach((item, index) => {
            fd.append(`items[${index}][description]`, item.description);
            fd.append(`items[${index}][line_type]`, item.line_type);
            fd.append(`items[${index}][quantity]`, item.line_type === 'activity' ? '1' : item.quantity || '1');
            if (item.hours) fd.append(`items[${index}][hours]`, item.hours);
            if (item.unit) fd.append(`items[${index}][unit]`, item.unit);
            if (item.period) fd.append(`items[${index}][period]`, item.period);
            fd.append(`items[${index}][unit_cost]`, item.unit_cost);
        });
        data.attachments.forEach((file) => fd.append('attachments[]', file));
        fd.append('submit', submitNow ? '1' : '0');

        post('/requisitions', { data: fd, forceFormData: true } as any);
    };

    return (
        <AppLayout
            title="New Internal Requisition"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Requisitions', href: '/requisitions' }, { label: 'New' }]}
        >
            <Head title="New Requisition" />

            <div className="mb-4 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                Requesting payment for <strong>development or support activities</strong>? Use the <strong>Service</strong> or{' '}
                <strong>Activity</strong> line types below — describe the work (e.g. "Backend API development — milestone 2") and enter
                the hours x rate or the flat amount. No quantities needed.
            </div>

            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader title="Requisition details" subtitle="All fields marked with * are required" />
                    <CardBody className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Title" htmlFor="title" required error={errors.title}>
                                <TextInput id="title" placeholder="e.g. Payment for software development — milestone 2" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            </Field>
                            <Field label="Category" htmlFor="category_id" required error={errors.category_id}>
                                <SelectInput id="category_id" value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}>
                                    <option value="">Select a category</option>
                                    {(categories ?? []).map((c: any) => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </SelectInput>
                            </Field>
                            <Field label="Project / budget reference (optional)" htmlFor="project_id">
                                <SelectInput id="project_id" value={data.project_id} onChange={(e) => setData('project_id', e.target.value)}>
                                    <option value="">None</option>
                                    {(projects ?? []).map((p: any) => (
                                        <option key={p.id} value={p.id}>{p.project_code} — {p.name}</option>
                                    ))}
                                </SelectInput>
                            </Field>
                            <Field label="Priority" htmlFor="priority" required>
                                <SelectInput id="priority" value={data.priority} onChange={(e) => setData('priority', e.target.value)}>
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </SelectInput>
                            </Field>
                            <Field label="Required date" htmlFor="required_date">
                                <TextInput id="required_date" type="date" value={data.required_date} onChange={(e) => setData('required_date', e.target.value)} />
                            </Field>
                            <Field label="Currency" htmlFor="currency" required>
                                <SelectInput id="currency" value={data.currency} onChange={(e) => setData('currency', e.target.value)}>
                                    {(currencies ?? ['UGX', 'USD']).map((c: string) => <option key={c} value={c}>{c}</option>)}
                                </SelectInput>
                            </Field>
                        </div>

                        <Field label="Activity description / purpose" htmlFor="purpose" error={errors.purpose}>
                            <TextArea id="purpose" rows={3} placeholder="Describe the activity this payment covers — e.g. development work completed, support period, deliverables…" value={data.purpose} onChange={(e) => setData('purpose', e.target.value)} />
                        </Field>
                        <Field label="Justification" htmlFor="justification" error={errors.justification}>
                            <TextArea id="justification" rows={3} value={data.justification} onChange={(e) => setData('justification', e.target.value)} />
                        </Field>

                        <div className="flex flex-wrap gap-6 rounded-md border border-gray-100 bg-gray-50 p-4">
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={data.is_advance} onChange={(e) => setData('is_advance', e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-green-600" />
                                This is an advance / accountable expenditure
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={data.accountability_required} onChange={(e) => setData('accountability_required', e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-green-600" />
                                Require accountability after disbursement
                            </label>
                        </div>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="Items & activities (optional)"
                        subtitle="Goods as quantity x cost · services as hours x rate · activities as a flat amount — or skip and enter the requested amount"
                        actions={<Button type="button" variant="secondary" size="sm" onClick={addItem}>Add line</Button>}
                    />
                    <CardBody className="space-y-3">
                        {(errors as any).items && <p className="text-xs font-medium text-red-600">{(errors as any).items}</p>}
                        {data.items.map((item, index) => (
                            <div key={index} className="rounded-lg border border-gray-100 p-3">
                                <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-12">
                                    <Field label="Line type" className="sm:col-span-2">
                                        <SelectInput value={item.line_type} onChange={(e) => setLineType(index, e.target.value as LineType)}>
                                            {(Object.keys(lineLabels) as LineType[]).map((type) => (
                                                <option key={type} value={type}>{lineLabels[type].name}</option>
                                            ))}
                                        </SelectInput>
                                    </Field>
                                    <Field
                                        label="Description"
                                        className="sm:col-span-5"
                                        required
                                        hint={lineLabels[item.line_type].hint}
                                    >
                                        <TextInput
                                            value={item.description}
                                            placeholder={item.line_type === 'activity' ? 'e.g. Software development — milestone 2 (API integration)' : item.line_type === 'service' ? 'e.g. On-site technical support — week 42' : 'e.g. Laptop stand'}
                                            onChange={(e) => updateItem(index, { description: e.target.value })}
                                        />
                                    </Field>

                                    {item.line_type === 'goods' && (
                                        <>
                                            <Field label="Qty" className="sm:col-span-1" required>
                                                <TextInput type="number" min="0.01" step="0.01" value={item.quantity} onChange={(e) => updateItem(index, { quantity: e.target.value })} />
                                            </Field>
                                            <Field label="Unit" className="sm:col-span-1">
                                                <TextInput value={item.unit} onChange={(e) => updateItem(index, { unit: e.target.value })} />
                                            </Field>
                                            <Field label="Unit cost" className="sm:col-span-2" required>
                                                <TextInput type="number" min="0" step="0.01" value={item.unit_cost} onChange={(e) => updateItem(index, { unit_cost: e.target.value })} />
                                            </Field>
                                        </>
                                    )}

                                    {item.line_type === 'service' && (
                                        <>
                                            <Field label="Hours" className="sm:col-span-2">
                                                <TextInput type="number" min="0" step="0.25" value={item.hours} onChange={(e) => updateItem(index, { hours: e.target.value })} />
                                            </Field>
                                            <Field label="Rate / hr" className="sm:col-span-2" required>
                                                <TextInput type="number" min="0" step="0.01" value={item.unit_cost} onChange={(e) => updateItem(index, { unit_cost: e.target.value })} />
                                            </Field>
                                            <Field label="Period" className="sm:col-span-2">
                                                <TextInput value={item.period} placeholder="e.g. Oct 2026" onChange={(e) => updateItem(index, { period: e.target.value })} />
                                            </Field>
                                        </>
                                    )}

                                    {item.line_type === 'activity' && (
                                        <>
                                            <Field label="Amount" className="sm:col-span-3" required>
                                                <TextInput type="number" min="0" step="0.01" value={item.unit_cost} onChange={(e) => updateItem(index, { unit_cost: e.target.value })} />
                                            </Field>
                                            <Field label="Period / milestone" className="sm:col-span-2">
                                                <TextInput value={item.period} placeholder="e.g. Milestone 2" onChange={(e) => updateItem(index, { period: e.target.value })} />
                                            </Field>
                                        </>
                                    )}

                                    <div className="flex items-center justify-between gap-2 sm:col-span-1">
                                        <p className="text-sm font-semibold text-ink-900 tabular">{formatMoney(lineTotal(item).toFixed(2), data.currency)}</p>
                                        <Button type="button" variant="danger" size="sm" onClick={() => removeItem(index)}>×</Button>
                                    </div>
                                </div>
                            </div>
                        ))}

                        <div className="rounded-md bg-green-50 px-4 py-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div className="text-sm text-green-800">
                                    {data.items.length > 0 ? (
                                        <>Sum of lines: <strong className="tabular">{formatMoney(recalcTotal(data.items), data.currency)}</strong></>
                                    ) : (
                                        <>No lines added — enter the amount requested.</>
                                    )}
                                </div>
                                <Field label="Requested amount" required className="w-full sm:w-64">
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={data.requested_amount}
                                        onChange={(e) => setData('requested_amount', e.target.value)}
                                    />
                                </Field>
                            </div>
                        </div>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Supporting documents (optional)" subtitle="Quotations, timesheets, delivery notes or other evidence (PDF, images, office documents) — not required to submit" />
                    <CardBody>
                        <input
                            type="file"
                            multiple
                            onChange={(e) => setData('attachments', Array.from(e.target.files ?? []))}
                            className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700 hover:file:bg-green-100"
                        />
                        {(errors as any).attachments && <p className="mt-1 text-xs text-red-600">{(errors as any).attachments}</p>}
                    </CardBody>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={submitNow} onChange={(e) => setSubmitNow(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-green-600" />
                        Submit for approval immediately (Technical Lead → HR → MD)
                    </label>
                    <div className="flex gap-2">
                        <Link href="/requisitions">
                            <Button type="button" variant="secondary">Cancel</Button>
                        </Link>
                        <Button type="submit" loading={processing}>{submitNow ? 'Submit requisition' : 'Save draft'}</Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
