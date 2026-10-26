import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { Head, useForm } from '@inertiajs/react';

export default function SettingsIndex({ company, uploads }: any) {
    const form = useForm<Record<string, any>>({
        'company.name': company?.name ?? '',
        'company.email': company?.email ?? '',
        'company.phone': company?.phone ?? '',
        'company.address': company?.address ?? '',
        'company.website': company?.website ?? '',
        'finance.currency': company?.currency ?? 'UGX',
        'app.timezone': company?.timezone ?? 'UTC',
        'uploads.max_bytes': String(uploads?.max_bytes ?? 20480),
        'contracts.reminder_days': '90,60,30',
        'workflow.allow_return_for_correction': true,
    });

    return (
        <AppLayout title="System Settings" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'System Settings' }]}>
            <Head title="System Settings" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/settings');
                }}
                className="space-y-6"
            >
                <Card>
                    <CardHeader title="Company profile" subtitle="Displayed on receipts and external requisition forms" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Company name" required error={form.errors['company.name']}>
                            <TextInput value={form.data['company.name']} onChange={(e) => form.setData('company.name', e.target.value)} />
                        </Field>
                        <Field label="Company email" error={form.errors['company.email']}>
                            <TextInput value={form.data['company.email']} onChange={(e) => form.setData('company.email', e.target.value)} />
                        </Field>
                        <Field label="Phone">
                            <TextInput value={form.data['company.phone']} onChange={(e) => form.setData('company.phone', e.target.value)} />
                        </Field>
                        <Field label="Address">
                            <TextInput value={form.data['company.address']} onChange={(e) => form.setData('company.address', e.target.value)} />
                        </Field>
                        <Field label="Website">
                            <TextInput value={form.data['company.website']} onChange={(e) => form.setData('company.website', e.target.value)} />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Localisation & documents" />
                    <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Default currency" required>
                            <TextInput value={form.data['finance.currency']} onChange={(e) => form.setData('finance.currency', e.target.value)} />
                        </Field>
                        <Field label="Company timezone" required>
                            <TextInput value={form.data['app.timezone']} onChange={(e) => form.setData('app.timezone', e.target.value)} />
                        </Field>
                        <Field label="Maximum upload size (KB)">
                            <TextInput type="number" value={form.data['uploads.max_bytes']} onChange={(e) => form.setData('uploads.max_bytes', e.target.value)} />
                        </Field>
                        <Field label="Contract reminder days (comma separated)">
                            <TextInput value={form.data['contracts.reminder_days']} onChange={(e) => form.setData('contracts.reminder_days', e.target.value)} />
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Approval workflow" subtitle="The mandatory Technical Lead → HR → MD sequence cannot be bypassed" />
                    <CardBody>
                        <label className="flex items-center gap-2 text-sm text-gray-800">
                            <input
                                type="checkbox"
                                checked={form.data['workflow.allow_return_for_correction']}
                                onChange={(e) => form.setData('workflow.allow_return_for_correction', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-green-600"
                            />
                            Allow approvers to return requisitions for correction
                        </label>
                        <p className="mt-2 text-xs text-gray-500">
                            Changing workflow configuration is recorded in the audit log. The required approval sequence for Technical
                            Department requisitions always remains enforced.
                        </p>
                    </CardBody>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" loading={form.processing}>Save settings</Button>
                </div>
            </form>
        </AppLayout>
    );
}
