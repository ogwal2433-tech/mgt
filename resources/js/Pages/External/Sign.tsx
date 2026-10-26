import { FormEventHandler, useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import SignaturePad from '@/Components/SignaturePad';
import Button from '@/Components/ui/Button';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate } from '@/types/app';

export default function ExternalSign({ requisition: r, company, signed, justSubmitted }: any) {
    const { data, setData, post, processing, errors } = useForm({
        requested_by_name: r.requested_by_name ?? r.external_contact ?? '',
        payee_name: r.payee_name ?? r.external_party_name,
        mode_of_payment: r.mode_of_payment ?? 'bank_transfer',
        purpose: r.purpose ?? '',
        items: (r.items ?? []).map((item: any) => ({
            id: item.id,
            description: item.description,
            total_price: item.total_price,
            actual_cost: item.actual_cost || '',
        })),
        signature: '' as string,
    });

    const actualTotal = useMemo(
        () => data.items.reduce((sum: number, item: any) => sum + (parseFloat(item.actual_cost || '0') || 0), 0),
        [data.items],
    );
    const unspent = (parseFloat(r.total_amount || '0') - actualTotal).toFixed(2);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/share/external-requisitions/${r.share_token ?? ''}`);
    };

    return (
        <div className="min-h-screen bg-ink-50 py-8">
            <Head title={`${r.requisition_number} — Requisition form`} />

            <div className="mx-auto max-w-4xl px-4">
                {/* Company header — like the printed form, refined */}
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04),0_16px_40px_-16px_rgba(16,24,40,0.12)]">
                    <div className="bg-ink-950 px-8 py-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-xl font-extrabold text-white shadow-lg shadow-brand-600/30">
                                    B
                                </div>
                                <div>
                                    <p className="text-2xl font-extrabold tracking-tight text-white">Biiteek</p>
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-300">
                                        Reliable, Fast &amp; Secure
                                    </p>
                                </div>
                            </div>
                            <div className="text-left sm:text-right">
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-brand-400">
                                    Requisition / Accountability Form
                                </p>
                                <p className="mt-1 text-lg font-bold text-white">{r.requisition_number}</p>
                                <p className="text-xs text-ink-300">Date: {formatDate(r.date)}</p>
                            </div>
                        </div>
                    </div>

                    {justSubmitted && (
                        <div className="border-b border-brand-200 bg-brand-50 px-8 py-5">
                            <p className="text-base font-bold text-brand-900">Thank you — your form has been submitted.</p>
                            <p className="mt-1 text-sm text-brand-800">
                                A signed copy has been emailed to {r.external_email ?? 'you'}. Our team will process the
                                requisition and contact you if anything else is needed.
                            </p>
                        </div>
                    )}

                    {signed && !justSubmitted && (
                        <div className="border-b border-gray-200 bg-ink-50 px-8 py-5">
                            <p className="text-sm font-semibold text-ink-800">
                                This form was signed by {r.signed_by_name} on {formatDate(r.signed_at)} and has been submitted.
                            </p>
                        </div>
                    )}

                    <form onSubmit={submit} className="px-8 py-6">
                        {/* Form fields — the printed-form header row, refined */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Field label="Requested by" required error={errors.requested_by_name}>
                                <TextInput
                                    value={data.requested_by_name}
                                    disabled={signed}
                                    onChange={(e) => setData('requested_by_name', e.target.value)}
                                />
                            </Field>
                            <Field label="Payee" required error={errors.payee_name}>
                                <TextInput
                                    value={data.payee_name}
                                    disabled={signed}
                                    onChange={(e) => setData('payee_name', e.target.value)}
                                />
                            </Field>
                            <Field label="Dept / organisation">
                                <TextInput value={r.department_text ?? r.external_party_name} disabled />
                            </Field>
                            <Field label="Mode of payment" required error={errors.mode_of_payment}>
                                <SelectInput
                                    value={data.mode_of_payment}
                                    disabled={signed}
                                    onChange={(e) => setData('mode_of_payment', e.target.value)}
                                >
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
                                <TextInput type="date" value={r.date_of_activity ?? ''} disabled />
                            </Field>
                            <Field label="Activity budgeted for">
                                <TextInput value={r.activity_budget ? formatMoney(r.activity_budget, r.currency) : '—'} disabled />
                            </Field>
                        </div>

                        <Field label="Activity description" className="mt-4" required error={errors.purpose}>
                            <TextArea
                                rows={3}
                                value={data.purpose}
                                disabled={signed}
                                onChange={(e) => setData('purpose', e.target.value)}
                            />
                        </Field>

                        {/* Items table */}
                        <h2 className="mt-8 text-xs font-bold uppercase tracking-[0.14em] text-ink-500">
                            Itemised activity — enter the actual cost for each line
                        </h2>
                        <div className="mt-2 overflow-x-auto rounded-xl border border-gray-200">
                            <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                                <thead>
                                    <tr className="bg-ink-900 text-white">
                                        <th className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider">Item description</th>
                                        <th className="px-4 py-2.5 text-right text-2xs font-semibold uppercase tracking-wider">Estimated cost</th>
                                        <th className="px-4 py-2.5 text-right text-2xs font-semibold uppercase tracking-wider">Actual cost</th>
                                        <th className="px-4 py-2.5 text-right text-2xs font-semibold uppercase tracking-wider">Balance</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {data.items.map((item: any, index: number) => {
                                        const balance = (parseFloat(item.total_price || '0') - (parseFloat(item.actual_cost || '0') || 0)).toFixed(2);
                                        return (
                                            <tr key={item.id}>
                                                <td className="px-4 py-2.5 text-ink-900">{item.description}</td>
                                                <td className="px-4 py-2.5 text-right tabular">{formatMoney(item.total_price, r.currency)}</td>
                                                <td className="px-4 py-2.5">
                                                    <TextInput
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        className="w-36 text-right"
                                                        value={item.actual_cost}
                                                        disabled={signed}
                                                        onChange={(e) => {
                                                            const items = data.items.map((it: any, i: number) =>
                                                                i === index ? { ...it, actual_cost: e.target.value } : it,
                                                            );
                                                            setData('items', items);
                                                        }}
                                                    />
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular text-ink-600">{formatMoney(balance, r.currency)}</td>
                                            </tr>
                                        );
                                    })}
                                    <tr className="bg-brand-50 font-semibold">
                                        <td className="px-4 py-2.5" colSpan={1}>TOTAL</td>
                                        <td className="px-4 py-2.5 text-right tabular">{formatMoney(r.total_amount, r.currency)}</td>
                                        <td className="px-4 py-2.5 text-right tabular">{formatMoney(actualTotal.toFixed(2), r.currency)}</td>
                                        <td className="px-4 py-2.5 text-right tabular">{formatMoney(unspent, r.currency)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-3 flex flex-wrap gap-x-8 gap-y-1 text-sm text-ink-600">
                            <p>Estimated total: <strong className="tabular">{formatMoney(r.total_amount, r.currency)}</strong></p>
                            <p>Actual total: <strong className="tabular">{formatMoney(actualTotal.toFixed(2), r.currency)}</strong></p>
                            <p>Unspent funds: <strong className="tabular text-brand-700">{formatMoney(unspent, r.currency)}</strong></p>
                        </div>

                        {/* Signature */}
                        {!signed && (
                            <div className="mt-8">
                                <SignaturePad
                                    label="Authorised signature"
                                    onChange={(value) => setData('signature', value ?? '')}
                                />
                                {(errors as any).signature && (
                                    <p className="mt-1 text-xs font-semibold text-red-600">{(errors as any).signature}</p>
                                )}
                            </div>
                        )}

                        {signed ? (
                            <div className="mt-8 rounded-xl border border-gray-200 bg-ink-50 p-5">
                                <p className="eyebrow">Signed by</p>
                                <p className="mt-1 text-base font-bold text-ink-900">{r.signed_by_name}</p>
                                <p className="text-xs text-ink-500">{formatDate(r.signed_at)}</p>
                            </div>
                        ) : (
                            <div className="mt-8 flex flex-col items-start justify-between gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center">
                                <p className="text-xs text-ink-500">
                                    By signing and submitting, you confirm the details on this form are accurate.
                                    You will receive a signed copy by email.
                                </p>
                                <Button type="submit" loading={processing} size="lg">
                                    Sign &amp; submit form
                                </Button>
                            </div>
                        )}
                    </form>

                    {/* Footer — Finance & Ops style */}
                    <div className="border-t border-gray-200 bg-ink-50 px-8 py-5">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 text-xs text-ink-500">
                            <div>
                                <p className="font-bold uppercase tracking-wider text-ink-600">Prepared by</p>
                                <p className="mt-1 text-ink-800">{r.prepared_by ?? company?.name}</p>
                            </div>
                            <div>
                                <p className="font-bold uppercase tracking-wider text-ink-600">For review</p>
                                <p className="mt-1 text-ink-800">Biiteek Finance &amp; Operations</p>
                            </div>
                            <div>
                                <p className="font-bold uppercase tracking-wider text-ink-600">Verification reference</p>
                                <p className="mt-1 font-mono text-ink-800">{r.requisition_number}-VER</p>
                            </div>
                        </div>
                        <p className="mt-4 text-[11px] text-ink-400">
                            © {new Date().getFullYear()} {company?.name ?? 'Biiteek'} — Reliable, Fast &amp; Secure. This form is
                            generated by the Biiteek Management System.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
