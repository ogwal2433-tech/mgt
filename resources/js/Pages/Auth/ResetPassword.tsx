import Button from '@/Components/ui/Button';
import { Field, PasswordInput, TextInput } from '@/Components/ui/Form';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <GuestLayout>
            <Head title="Reset password — Biiteek" />

            <div className="mb-7">
                <p className="eyebrow">Account recovery</p>
                <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-ink-900">Set a new password</h1>
                <p className="mt-2 text-sm leading-relaxed text-ink-500">
                    Choose a strong password you have not used before.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <Field label="Email address" htmlFor="email" error={errors.email} required>
                    <TextInput id="email" type="email" value={data.email} autoComplete="username" onChange={(e) => setData('email', e.target.value)} />
                </Field>

                <Field label="New password" htmlFor="password" error={errors.password} required>
                    <PasswordInput id="password" value={data.password} autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} />
                </Field>

                <Field label="Confirm new password" htmlFor="password_confirmation" required>
                    <PasswordInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </Field>

                <Button type="submit" loading={processing} className="w-full" size="lg">
                    {processing ? 'Resetting…' : 'Reset password'}
                </Button>
            </form>
        </GuestLayout>
    );
}
