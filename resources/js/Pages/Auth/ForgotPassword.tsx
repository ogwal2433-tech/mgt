import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <GuestLayout>
            <Head title="Forgot password — Biiteek" />

            <div className="mb-7">
                <p className="eyebrow">Account recovery</p>
                <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-ink-900">Forgot your password?</h1>
                <p className="mt-2 text-sm leading-relaxed text-ink-500">
                    Enter your company email address and we will send you a secure reset link.
                </p>
            </div>

            {status && (
                <div className="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <Field label="Email address" htmlFor="email" error={errors.email} required>
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        autoFocus
                        autoComplete="username"
                        placeholder="you@biiteek.example"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>

                <Button type="submit" loading={processing} className="w-full" size="lg">
                    {processing ? 'Sending link…' : 'Email reset link'}
                </Button>

                <p className="text-center text-xs text-ink-500">
                    Remembered it?{' '}
                        <a href="/login" className="link-clean">
                            Back to sign in
                        </a>
                </p>
            </form>
        </GuestLayout>
    );
}
