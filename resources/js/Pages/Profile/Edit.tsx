import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, PasswordInput, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ProfileEdit({ mustVerifyEmail, mustChangePassword, status }: { mustVerifyEmail?: boolean; mustChangePassword?: boolean; status?: string }) {
    const user = usePage().props.auth.user as any;

    const profile = useForm({ name: user.name, email: user.email });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submitProfile: FormEventHandler = (e) => {
        e.preventDefault();
        profile.patch('/profile', { preserveScroll: true });
    };

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        password.put('/password', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    return (
        <AppLayout title="Profile" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Profile' }]}>
            <Head title="Profile" />

            {mustChangePassword && (
                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div className="flex items-start gap-3">
                        <svg className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-2.99L13.73 4.01c-.77-1.33-2.69-1.33-3.46 0L3.34 16.01C2.57 17.33 3.53 19 5.07 19z" />
                        </svg>
                        <div>
                            <p className="text-sm font-bold text-amber-900">You must set a new password</p>
                            <p className="mt-1 text-sm leading-relaxed text-amber-800">
                                You signed in with a temporary password. Please choose a new password below — it takes effect
                                immediately and the temporary one stops working.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {status && (
                <div className="mb-4 rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                    {status}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Profile information" subtitle="Your name and company email address" />
                    <form onSubmit={submitProfile} className="space-y-4 px-5 py-4">
                        <Field label="Username">
                            <TextInput value={user.username ?? ''} disabled />
                        </Field>
                        <Field label="Full name" required error={profile.errors.name}>
                            <TextInput value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} />
                        </Field>
                        <Field label="Email" required error={profile.errors.email}>
                            <TextInput type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} />
                        </Field>
                        {mustVerifyEmail && user.email_verified_at === null && (
                            <p className="text-xs text-amber-700">Your email address is unverified.</p>
                        )}
                        <div className="flex justify-end">
                            <Button type="submit" loading={profile.processing}>Save</Button>
                        </div>
                    </form>
                </Card>

                <Card>
                    <CardHeader
                        title="Change password"
                        subtitle={mustChangePassword ? 'Required — set your personal password now' : 'Use at least 8 characters'}
                    />
                    <form onSubmit={submitPassword} className="space-y-4 px-5 py-4">
                        <Field label="Current password" required error={password.errors.current_password} hint={mustChangePassword ? 'This is the temporary password from your invitation email.' : undefined}>
                            <PasswordInput value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} />
                        </Field>
                        <Field label="New password" required error={password.errors.password}>
                            <PasswordInput value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} />
                        </Field>
                        <Field label="Confirm new password" required>
                            <PasswordInput value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} />
                        </Field>
                        <div className="flex justify-end">
                            <Button type="submit" loading={password.processing}>
                                {mustChangePassword ? 'Set new password' : 'Update password'}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
