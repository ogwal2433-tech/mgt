import Button from '@/Components/ui/Button';
import { Field, PasswordInput, TextInput } from '@/Components/ui/Form';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/confirm-password', { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Confirm password — Biiteek" />

            <div className="mb-7">
                <p className="eyebrow">Security check</p>
                <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-ink-900">Confirm your password</h1>
                <p className="mt-2 text-sm leading-relaxed text-ink-500">
                    This is a sensitive area. Please confirm your password before continuing.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <Field label="Password" htmlFor="password" error={errors.password} required>
                    <PasswordInput
                        id="password"
                        value={data.password}
                        autoFocus
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <Button type="submit" loading={processing} className="w-full" size="lg">
                    {processing ? 'Confirming…' : 'Confirm password'}
                </Button>
            </form>
        </GuestLayout>
    );
}
